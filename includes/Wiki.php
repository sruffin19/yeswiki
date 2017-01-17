<?php
require_once('includes/Action.php');
require_once('includes/Database.php');
require_once('includes/Triples.php');
require_once('includes/Cookies.php');
require_once('includes/Inclusions.php');
require_once('includes/UserFactory.php');

use YesWiki\Database;
use YesWiki\Triples;
use YesWiki\Cookies;
use YesWiki\Inclusions;
use YesWiki\Actions;
use YesWiki\UserFactory;

class Wiki extends Actions
{
    public $page;
    public $tag;
    public $config;
    public $inclusions;
    public $parameter = array();
    public $queryLog = array();
    public $triples = null;
    public $cookies = null;
    public $connectedUser = null;

    /**
     * LinkTrackink
     */
    public $isTrackingLinks = false;
    public $linktable = array();
    public $pageCache = array();
    public $groupsCache = array();
    public $actionsAclsCache = array();

    /**
     * Caching page ACLs (sql query on yeswiki_acls table).
     * Filled in loadAcl().
     * Updated in saveAcl().
     * @var array
     */
    protected $aclsCache = array() ;

    // constructor
    public function __construct($config)
    {
        $this->config = $config;

        // TODO Utiliser plutôt de l'injection de dépendance
        $this->database = new Database(
            $this->getConfigValue('mysql_host'),
            $this->getConfigValue('mysql_user'),
            $this->getConfigValue('mysql_password'),
            $this->getConfigValue('mysql_database'),
            $this->getConfigValue('table_prefix')
        );
        $this->triples = new Triples($this->database);
        $this->cookies = new Cookies($this->config['base_url']);
        $this->inclusions = new Inclusions();
    }

    public function includeBuffered($filename, $notfoundText = '', $vars = '', $path = '')
    {
        $dirs = array('');
        if ($path) {
            $dirs = explode(':', $path);
        }

        $included['before'] = array();
        $included['new'] = array();
        $included['after'] = array();

        foreach ($dirs as $dir) {
            if ($dir) {
                $dir .= '/';
            }

            $fullfilename = $dir . $filename;

            $beforefullfilename = $dir . '__' . $filename;
            if (strstr($filename, 'page/')) {
                list ($file, $extension) = explode('page/', $filename);
                $beforefullfilename = $dir . $file . 'page/__' . $extension;
            }

            list ($file, $extension) = explode('.', $filename);
            $afterfullfilename = $dir . $file . '__.' . $extension;

            if (file_exists($beforefullfilename)) {
                $included['before'][] = $beforefullfilename;
            }

            if (file_exists($fullfilename)) {
                $included['new'][] = $fullfilename;
            }

            if (file_exists($afterfullfilename)) {
                $included['after'][] = $afterfullfilename;
            }
        }

        $pluginOutputNew = '';
        $found = 0;

        if (is_array($vars)) {
            extract($vars);
        }

        foreach ($included['before'] as $before) {
            $found = 1;
            ob_start();
            include($before);
            $pluginOutputNew .= ob_get_contents();
            ob_end_clean();
        }

        foreach ($included['new'] as $new) {
            $found = 1;
            ob_start();
            require($new);
            $pluginOutputNew = ob_get_contents();
            ob_end_clean();
            break;
        }

        foreach ($included['after'] as $after) {
            $found = 1;
            ob_start();
            include($after);
            $pluginOutputNew .= ob_get_contents();
            ob_end_clean();
        }

        if ($found) {
            return $pluginOutputNew;
        }

        if ($notfoundText) {
            return $notfoundText;
        }

        return false;
    }

    // VARIABLES
    public function getPageTag()
    {
        return $this->tag;
    }

    public function getPageTime()
    {
        return $this->page['time'];
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getConfigValue($name, $default = null)
    {
        return isset($this->config[$name])
            ? trim($this->config[$name])
            : ($default != null ? $default : '') ;
    }

    public function getWakkaName()
    {
        return $this->getConfigValue('wakka_name');
    }

    public function getWakkaVersion()
    {
        return WAKKA_VERSION;
    }

    public function getWikiNiVersion()
    {
        return WIKINI_VERSION;
    }

    // PAGES
    public function loadPage($tag, $time = "", $cache = 1)
    {
        // retrieve from cache
        if (empty($time)
            and $cache
            and (($cachedPage = $this->getCachedPage($tag)) !== false)
        ) {
            return $cachedPage;
        }

        $table = $this->config['table_prefix'] . 'pages';
        $tag = $this->database->escapeString($tag);
        $strTime = $time
            ? "time = '" . $this->database->escapeString($time) . "'"
            : "latest = 'Y'";

        $sql = "SELECT * FROM $table WHERE tag = '$tag' AND $strTime LIMIT 1";
        $page = $this->database->loadSingle($sql);

        // the database is in ISO-8859-15, it must be converted
        if (isset($page['body'])) {
            $page['body'] = _convert($page['body'], 'ISO-8859-15');
        }

        // cache result
        if (! $time) {
            $this->cachePage($page, $tag);
        }

        return $page;
    }

    /**
     * Retrieves the cached version of a page.
     *
     * Notice that this method null or false, use
     * $this->getCachedPage($tag) === false
     * to check if a page is not in the cache.
     *
     * @return mixed The cached version of a page:
     *         - the page DB line if the page exists and is in cache
     *         - null if the cache knows that the page does not exists
     *         - false is the cache does not know the page
     */
    public function getCachedPage($tag)
    {
        if (array_key_exists($tag, $this->pageCache)) {
            return $this->pageCache[$tag];
        }
        return false;
    }

    /**
     * Caches a page's DB line.
     *
     * @param array $page
     *            The page (full) DB line or null if the page does not exists
     * @param string $pageTag
     *            The tag of the page to cache. Defaults to $page['tag'] but is
     *            mendatory when $page === null
     */
    public function cachePage($page, $pageTag = null)
    {
        if ($pageTag === null) {
            $pageTag = $page['tag'];
        }
        $this->pageCache[$pageTag] = $page;
    }

    public function setPage($page)
    {
        $this->page = $page;
        if ($this->page['tag']) {
            $this->tag = $this->page['tag'];
        }
    }

    public function loadPageById($pageId)
    {
        $table = $this->database->prefix . 'pages';
        $pageId = $this->database->escapeString($pageId);
        return $this->database->loadSingle(
            "SELECT * FROM $table WHERE id = '$pageId' LIMIT 1"
        );
    }

    public function loadRevisions($page)
    {
        $table = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($page);

        return $this->database->loadAll(
            "SELECT * FROM $table WHERE tag = '$tag' ORDER BY time DESC"
        );
    }

    public function loadPagesLinkingTo($tag)
    {
        $table = $this->database->prefix . 'links';
        $tag = $this->database->escapeString($tag);
        return $this->database->loadAll(
            "SELECT from_tag AS tag FROM $table
                WHERE to_tag = '$tag' ORDER BY tag"
        );
    }

    public function loadRecentlyChanged($limit = 50)
    {
        $limit = (int) $limit;
        $table = $this->database->prefix . 'pages';
        if ($pages = $this->database->loadAll(
            "SELECT id, tag, time, user, owner FROM $table
                WHERE latest = 'Y' AND comment_on = ''
                ORDER BY time DESC LIMIT $limit"
        )) {
            foreach ($pages as $page) {
                $this->cachePage($page);
            }

            return $pages;
        }
    }

    public function getPageCreateTime($pageTag)
    {
        $table = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($pageTag);
        $sql = "SELECT time FROM $table
                    WHERE tag = '$tag' AND comment_on = \"\"
                    ORDER BY `time` ASC LIMIT 1";
        if ($page = $this->database->loadSingle($sql)) {
            return $page['time'];
        }
        return null ;
    }

    public function isOrphanedPage($tag)
    {
        $tablePages = $this->database->prefix . 'pages';
        $tableLinks = $this->database->prefix . 'links';
        $tag = $this->database->escapeString($tag);

        return $this->database->loadAll(
            "SELECT DISTINCT tag FROM $tablePages
                AS p LEFT JOIN $tableLinks AS l ON p.tag = l.to_tag
                WHERE l.to_tag IS NULL AND p.latest = 'Y' AND tag = '$tag'"
        );
    }

    public function deleteOrphanedPage($tag)
    {
        $tablePages = $this->database->prefix . 'pages';
        $tableLinks = $this->database->prefix . 'links';
        $tableAcls = $this->database->prefix . 'acls';
        $tableReferrers = $this->database->prefix . 'referrers';
        $tag = $this->database->escapeString($tag);

        $this->database->query(
            "DELETE FROM $tablePages WHERE tag='$tag' OR comment_on='$tag'"
        );
        $this->database->query("DELETE FROM $tableLinks WHERE from_tag='$tag'");
        $this->database->query("DELETE FROM $tableAcls WHERE page_tag='$tag'");
        $this->database->query("DELETE FROM $tableReferrers WHERE page_tag='$tag'");
    }

    /**
     * savePage
     * Sauvegarde un contenu dans une page donnee
     *
     * @param string $body
     *            Contenu a sauvegarder dans la page
     * @param string $tag
     *            Nom de la page
     * @param string $commentOn
     *            Indication si c'est un commentaire
     * @param boolean $bypassAcls
     *            Indication si on bypasse les droits d'ecriture
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     *
     */
    public function savePage($tag, $body, $commentOn = "", $bypassAcls = false)
    {
        // get current user
        $user = $this->getUserName();

        // check bypass of rights or write privilege
        $rights = $bypassAcls
            || ($commentOn ? $this->hasAccess('comment', $commentOn) : $this->hasAccess('write', $tag));

        if ($rights) {
            // is page new?
            if (! $oldPage = $this->loadPage($tag)) {
                // create default write acl. store empty write ACL for comments.
                $this->saveAcl($tag, 'write', ($commentOn ? $user : $this->getConfigValue('default_write_acl')));

                // create default read acl
                $this->saveAcl($tag, 'read', $this->getConfigValue('default_read_acl'));

                // create default comment acl.
                $this->saveAcl($tag, 'comment', ($commentOn ? '' : $this->getConfigValue('default_comment_acl')));

                // current user is owner; if user is logged in! otherwise, no owner.
                $owner = '';
                if (!is_null($this->connectedUser)) {
                    $owner = $this->connectedUser->name;
                }
            } else {
                // aha! page isn't new. keep owner!
                $owner = $oldPage['owner'];

                // ...and comment_on, eventualy?
                if ($commentOn == '') {
                    $commentOn = $oldPage['comment_on'];
                }
            }


            $tablePages = $this->database->prefix . 'pages';
            $strTag = $this->database->escapeString($tag);

            // set all other revisions to old
            $this->database->query(
                "UPDATE $tablePages SET latest = 'N' WHERE tag = '$strTag'"
            );

            $commentStr = "";
            if($commentOn) {
                $commentStr = "comment_on = '"
                    . $this->database->escapeString($commentOn)
                    . "', ";
            }
            $owner = $this->database->escapeString($owner);
            $user = $this->database->escapeString($user);
            $body = $this->database->escapeString(chop($body));

            // add new revision
            $this->database->query(
                "INSERT INTO $tablePages
                    SET tag = '$strTag',
                        $commentStr
                        time = now(),
                        owner = '$owner',
                        user = '$user',
                        latest = 'Y',
                        body = '$body',
                        body_r = ''"
            );

            unset($this->pageCache[$tag]);
            return 0;
        }

        return 1;
    }

    /**
     * appendContentToPage
     * Ajoute du contenu a la fin d'une page
     *
     * @param string $content
     *            Contenu a ajouter a la page
     * @param string $page
     *            Nom de la page
     * @param boolean $bypassAcls
     *            Bouleen pour savoir s'il faut bypasser les ACLs
     * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
     *
     * TODO : ne sert que dans la fonction logAdministrativeAction, semble
     * inutilement compliqué. a vérifier sans compter le booléen passé en paramêtre
     */
    public function appendContentToPage($content, $page)
    {
        // -- Determine quelle est la page :
        // -- passee en parametre (que se passe-t'il si elle n'existe pas ?)
        // -- ou la page en cours par defaut
        $page = isset($page) ? $page : $this->getPageTag();

        // -- Chargement de la page
        $result = $this->loadPage($page);
        $body = $result['body'];
        // -- Ajout du contenu a la fin de la page
        $body .= $content;

        // -- Sauvegarde de la page
        // TODO : que se passe-t-il si la page est pleine ou si l'utilisateur n'a pas les droits ?
        $this->savePage($page, $body, '', true);

        // now we render it internally so we can write the updated link table.
        $this->clearLinkTable();
        $this->startLinkTracking();
        $temp = $this->setInclusions();
        $this->registerInclusion($this->getPageTag()); // on simule totalement un affichage normal
        $this->format($body);
        $this->setInclusions($temp);
        if (!is_null($this->connectedUser)) {
            $this->trackLinkTo($this->connectedUser->name);
        }
        if ($owner = $this->getPageOwner()) {
            $this->trackLinkTo($owner);
        }
        $this->stopLinkTracking();
        $this->writeLinkTable();
        $this->clearLinkTable();
    }

    /**
     * logAdministrativeAction($user, $content, $page = "")
     *
     * @param string $user
     *            Utilisateur
     * @param string $content
     *            Contenu de l'enregistrement
     * @param string $page
     *            Page de log
     *
     * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
     */
    public function logAdministrativeAction($user, $content, $page = '')
    {
        $order = array("\r\n", "\n", "\r");
        $replace = '\\n';
        $content = str_replace($order, $replace, $content);
        $contentToAppend = "\n" . date('Y-m-d H:i:s')
            . " . . . . $user"
            . " . . . . $content\n";
        $page = $page ? $page : 'LogDesActionsAdministratives' . date('Ymd');
        return $this->appendContentToPage($contentToAppend, $page);
    }

    /**
     * Make the purge of page versions that are older than the last version
     * older than 3 "pages_purge_time" This method permits to allways keep a
     * version that is older than that period.
     */
    public function purgePages()
    {
        if ($days = $this->getConfigValue('pages_purge_time')) {
            // is purge active ?
            // let's search which pages versions we have to remove
            // this is necessary beacause even MySQL does not handel multi-tables
            // deletes before version 4.0
            $wnPages = $this->getConfigValue('table_prefix') . 'pages';
            $day = addslashes($days);
            $sql = "SELECT DISTINCT a.id FROM $wnPages a, $wnPages b
                        WHERE a.latest = 'N'
                            AND a.time < date_sub(now(), INTERVAL '$day' DAY)
                            AND a.tag = b.tag
                            AND a.time < b.time
                            AND b.time < date_sub(now(), INTERVAL '$day' DAY)";

            $ids = $this->database->loadAll($sql);

            if (count($ids)) {
                // there are some versions to remove from DB
                // let's build one big request, that's better...
                $sql = "DELETE FROM $wnPages WHERE id IN (";
                foreach ($ids as $key => $line) {
                    // NB.: id is an int, no need of quotes
                    $sql .= ($key ? ', ' : '') . $line['id'];
                }
                $sql .= ')';

                // ... and send it !
                $this->database->query($sql);
            }
        }
    }

    // HTTP/REQUEST/LINK RELATED
    public function setMessage($message)
    {
        $_SESSION['message'] = $message;
    }


    /**
     * retourne et efface le message.
     * @return [type] [description]
     */
    public function getMessage()
    {
        $message = '';
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
        }

        $_SESSION['message'] = '';
        return $message;
    }

    public function redirect($url)
    {
        header("Location: $url");
        exit();
    }

    // returns just PageName[/method].
    public function miniHref($method = '', $tag = '')
    {
        if (! $tag = trim($tag)) {
            $tag = $this->tag;
        }

        return $tag . ($method ? '/' . $method : '');
    }

    // returns the full url to a page/method.
    public function href($method = '', $tag = '', $params = '', $htmlspchars = true)
    {
        $href = $this->config["base_url"] . $this->miniHref($method, $tag);
        if ($params) {
            $href .= ($this->config['rewrite_mode'] ? '?' : ($htmlspchars ? '&amp;' : '&')) . $params;
        }
        return $href;
    }

    // TODO à réécrire. trop complexe et pas lisible.
    public function link($tag, $method = "", $text = "", $track = 1)
    {
        $displayText = $text ? $text : $tag;
        $displayText = htmlspecialchars($displayText, ENT_COMPAT, YW_CHARSET);

        // is this a full link? ie, does it contain non alpha-numeric characters?
        // Note : [:alnum:] is equivalent [0-9A-Za-z]
        // [^[:alnum:]] means : some caracters other than [0-9A-Za-z]
        // For example : "www.adress.com", "mailto:adress@domain.com", "http://www.adress.com"

        // TODO utiliser filter_var plutot que ces regex bizarre
        if (preg_match('/[^[:alnum:]]/', $tag)) {
            // check for various modifications to perform on $tag
            if (!preg_match("/^[\w.-]+\@[\w.-]+$/", $tag)) {
                // Note : in Perl regexp, (?: ... ) is a non-catching cluster
                // Finally, block script schemes (see RFC 3986 about
                // schemes) and allow relative link & protocol-full URLs
                if (!preg_match('/^[[:alnum:]][[:alnum:].-]*(?:\/|$)/', $tag)) {
                    if (preg_match('/^[a-z0-9.+-]*script[a-z0-9.+-]*:/i', $tag)
                        or !(
                            preg_match('/^\.?\.?\//', $tag)
                            or preg_match('/^[a-z0-9.+-]+:\/\//i', $tag)
                        )
                    ) {
                        // If does't fit, we can't qualify $tag as an URL.
                        // There is a high risk that $tag is just XSS (bad
                        // javascript: code) or anything nasty. So we must not
                        // produce any link at all.
                        return htmlspecialchars(
                            $tag . ($text ? ' ' . $text : ''),
                            ENT_COMPAT,
                            YW_CHARSET
                        );
                    }
                }
                // protocol-less URLs
                $tag = 'http://' . $tag;
            }
            // email addresses
            $tag = 'mailto:' . $tag;
            // Important: Here, we know that $tag is not something bad
            // and that we must produce a link with it

            // An inline image? (text!=tag and url ends by png,gif,jpeg)
            if ($text and preg_match("/\.(gif|jpeg|png|jpg)$/i", $tag)) {
                $tag = htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET);
                return "<img src=\"$tag\" alt=\"$displayText\"/>";
            }
            // Even if we know $tag is harmless, we MUST encode it
            // in HTML with htmlspecialchars() before echoing it.
            // This is not about being paranoiac. This is about
            // being compliant to the HTML standard.
            $tag = htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET);
            return "<a href=\"$tag\">$displayText</a>";
        }

        // it's a Wiki link!
        if (!empty($track)) {
            $this->trackLinkTo($tag);
        }

        if ($this->loadPage($tag)) {
            $href = htmlspecialchars(
                $this->href($method, $tag),
                ENT_COMPAT,
                YW_CHARSET
            );
            return "<a href=\"$href\">$displayText</a>";
        }
        $href = htmlspecialchars(
            $this->href("edit", $tag),
            ENT_COMPAT,
            YW_CHARSET
        );
        return "<span class=\"missingpage\">$displayText</span>"
            . "<a href=\"$href\">?</a>";
    }

    //TODO : voir le parametre $track visiblement un booléen et c'est mal
    public function composeLinkToPage($tag, $method = "", $text = "", $track = 1)
    {
        if (! $text) {
            $text = $tag;
        }

        $text = htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
        if ($track) {
            $this->trackLinkTo($tag);
        }

        return '<a href="' . $this->href($method, $tag) . '">' . $text . '</a>';
    }

    public function isWikiName($text)
    {
        return preg_match('/^' . WN_CAMEL_CASE . '$/', $text);
    }

    /**
     * Call header action
     * TODO ugly
     */
    public function header()
    {
        $action = $this->getConfigValue('header_action');
        if (($actionObj = $this->getActionObject($action)) and is_object($actionObj)) {
            return $actionObj->GenerateHeader();
        }
        return $this->action($action, 1);
    }

    /**
     * call Footer action
     */
    public function footer()
    {
        $action = $this->getConfigValue('footer_action');
        if (($actionObj = $this->getActionObject($action)) and is_object($actionObj)) {
            return $actionObj->GenerateFooter();
        }
        return $this->action($action, 1);
    }

    // FORMS
    public function formOpen($method = '', $tag = '', $formMethod = 'post', $class = '')
    {
        $result = "<form action=\"" . $this->href($method, $tag) . "\" method=\"" . $formMethod . "\"";
        $result .= ((! empty($class)) ? " class=\"" . $class . "\"" : "");
        $result .= ">\n";
        if (! $this->config['rewrite_mode']) {
            $result .= "<input type=\"hidden\" name=\"wiki\" value=\"" . $this->miniHref($method, $tag) . "\" />\n";
        }

        return $result;
    }

    public function formClose()
    {
        return "</form>\n";
    }

    // REFERRERS
    // TODO : Appeler une seule fois et sans parametres... supprimer les parametres.
    public function logReferrer($tag = "", $referrer = "")
    {
        // fill values
        if (! $tag = trim($tag)) {
            $tag = $this->getPageTag();
        }

        if (! $referrer = trim($referrer) and isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        // check if it's coming from another site
        if ($referrer
            and !preg_match(
                '/^' . preg_quote($this->getConfigValue('base_url'), '/') . '/',
                $referrer
            )
        ) {
            // avoid XSS (with urls like "javascript:alert()" and co)
            // by forcing http/https prefix
            // NB.: this does NOT exempt to htmlspecialchars() the collected URIs !
            if (! preg_match('`^https?://`', $referrer)) {
                return;
            }

            $tableReferrers = $this->database->prefix . 'referrers';
            $tag = $this->database->escapeString($tag);
            $referrer = $this->database->escapeString($referrer);
            $this->database->query(
                "INSERT INTO $tableReferrers
                    SET page_tag = '$tag',
                        referrer = '$referrer',
                        time = now()"
            );
        }
    }

    public function loadReferrers($tag = "")
    {
        $tableReferrers = $this->database->prefix . 'referrers';
        $where = "";
        if ($tag = trim($tag)) {
            $where = "WHERE page_tag = '" . $this->database->escapeString($tag) . "'";
        }

        return $this->database->loadAll(
            "SELECT referrer, count(referrer) AS num
                FROM $tableReferrers
                $where
                GROUP BY referrer
                ORDER BY num DESC"
        );
    }

    //TODO appelé qu'une seule fois... necessaires ?
    public function purgeReferrers()
    {
        if ($days = $this->getConfigValue("referrers_purge_time")) {
            $tableReferrers = $this->database->prefix . 'referrers';
            $days = $this->database->escapeString($days);
            $this->database->query(
                "DELETE FROM $tableReferrers
                    WHERE time < date_sub(now(), interval '$days' day)"
            );
        }
    }

    /**
     * Retrieves the list of existing handlers
     *
     * @return array An unordered array of all the available handlers.
     *
     * TODO appeler une seule fois par une action..
     */
    public function getHandlersList()
    {
        $handlerPath = $this->getConfigValue('handler_path');
        $dirs = explode(":", $handlerPath);
        $list = array();
        foreach ($dirs as $dir) {
            $dir .= '/page';
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches)) {
                        $list[] = $matches[1];
                    }
                }
            }
        }
        return array_unique($list);
    }

    public function method($method)
    {
        if (! $handler = $this->page['handler']) {
            $handler = 'page';
        }

        $methodLocation = $handler . '/' . $method . '.php';
        return $this->includeBuffered(
            $methodLocation,
            '<i>' . _t('UNKNOWN_METHOD') . " \"$methodLocation\"</i>",
            "",
            $this->config['handler_path']
        );
    }

    function format($text, $formatter = 'wakka')
    {
        return $this->includeBuffered(
            $formatter . '.php',
            "<i>Impossible de trouver le formateur \"$formatter\"</i>",
            compact("text"),
            $this->config['formatter_path']
        );
    }

    public function getParameter($parameter, $default = '')
    {
        return (
            isset($this->parameter[$parameter])
                ? $this->parameter[$parameter]
                : $default
        );
    }

    // COMMENTS
    /**
     * Charge les commentaires relatifs a une page.
     *
     * @param string $tag
     *            Nom de la page. Ex : "PagePrincipale"
     * @return array Tableau contenant tous les commentaires et leurs
     *         proprietes correspondantes.
     */
    public function loadComments($tag)
    {
        $tablePages = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($tag);
        return $this->database->loadAll(
            "SELECT *
                FROM $tablePages
                WHERE comment_on = '$tag'
                    AND latest = 'Y'
                ORDER BY substring(tag, 8) + 0"
        );
    }

    /**
     * Charge les derniers commentaires de toutes les pages.
     *
     * @param int $limit
     *            Nombre de commentaires charges.
     *            0 par d?faut (ie tous les commentaires).
     * @return array Tableau contenant chaque commentaire et ses
     *         proprietes associees.
     * @todo Ajouter le parametre $start pour permettre une pagination
     *       des commentaires : ->loadRecentComments(10, 10)
     */
    public function loadRecentComments($limit = 0)
    {
        // The part of the query which limit the number of comments
        $lim = '';
        if (is_numeric($limit) and $limit > 0) {
            $lim = 'LIMIT ' . $limit;
        }
        // Query
        $tablePages = $this->database->prefix . 'pages';
        return $this->database->loadAll(
            "SELECT *
                FROM $tablePages
                WHERE comment_on != \"\"
                    AND latest = 'Y'
                ORDER BY time DESC
                $lim"
        );
    }

    public function loadRecentlyCommented($limit = 50)
    {
        $pages = array();

        // NOTE: this is really stupid. Maybe my SQL-Fu is too weak, but
        // apparently there is no easier way to simply select
        // all comment pages sorted by their first revision's (!) time. ugh!

        // load ids of the first revisions of latest comments. err, huh?

        $tablePages = $this->database->prefix . 'pages';
        $sql = "SELECT min(id) AS id
                    FROM $tablePages
                    WHERE comment_on != \"\"
                    GROUP BY tag
                    ORDER BY id DESC";

        if ($pages = $this->database->loadAll($sql)) {
            // load complete comments
            $num = 0;
            foreach ($pages as $page) {
                $pageId = $pageId['id'];
                $comment = $this->database->loadSingle(
                    "select * from ${prefix}pages where id = '$pageId' limit 1"
                );
                if (!isset($comments[$comment['comment_on']])
                    and $num < $limit
                ) {
                    $comments[$comment['comment_on']] = $comment;
                    $num ++;
                }
            }

            // now load pages
            if ($comments) {
                // now using these ids, load the actual pages
                foreach ($comments as $comment) {
                    $page = $this->loadPage($comment['comment_on']);
                    $page['comment_user'] = $comment['user'];
                    $page['comment_time'] = $comment['time'];
                    $page['comment_tag'] = $comment['tag'];
                    $pages[] = $page;
                }
            }
        }
        return $pages;
    }

    // ACCESS CONTROL


    /**
     *
     * @param string $group
     *            The name of a group
     * @return string the ACL associated with the group $gname
     * @see userIsInGroup to check if a user belongs to some group
     */
    public function getGroupACL($group)
    {
        if (array_key_exists($group, $this->groupsCache)) {
            return $this->groupsCache[$group];
        }
        return $this->groupsCache[$group] =
            $this->triples->getTripleValue($group, WIKINI_VOC_ACLS, GROUP_PREFIX);
    }

    /**
     * Checks if a new group acl is not defined recursively
     * (this method expects that groups that are already defined are not themselves defined recursively...)
     *
     * @param string $gname
     *            The name of the group
     * @param string $acl
     *            The new acl for that group
     * @return boolean True iff the new acl defines the group recursively
     */
    public function makesGroupRecursive($gname, $acl, $origin = null, $checked = array())
    {
        $gname = strtolower($gname);
        if ($origin === null) {
            $origin = $gname;
        } elseif ($gname === $origin) {
            return true;
        }

        foreach (explode("\n", $acl) as $line) {
            if (!$line) {
                continue;
            }

            if ($line[0] == '!') {
                $line = substr($line, 1);
            }

            if (!$line) {
                continue;
            }

            if ($line[0] == '@') {
                $line = substr($line, 1);
                if (! in_array($line, $checked)) {
                    if ($this->makesGroupRecursive(
                        $line,
                        $this->getGroupACL($line),
                        $origin,
                        $checked
                    )) {
                        return true;
                    }
                }
            }
        }
        $checked[] = $gname;
        return false;
    }

    /**
     * Sets a new ACL to a given group
     *
     * @param string $gname
     *            The name of a group
     * @param string $acl
     *            The new ACL to associate with the group $gname
     * @return int 0 if successful, a triple error code or a specific error code:
     *         1000 if the new value would define the group recursively
     *         1001 if $gname is not named with alphanumeric chars
     * @see getGroupACL
     */
    public function setGroupACL($gname, $acl)
    {
        if (preg_match('/[^A-Za-z0-9]/', $gname)) {
            return 1001;
        }
        $old = $this->getGroupACL($gname);
        if ($this->makesGroupRecursive($gname, $acl)) {
            return 1000;
        }
        $this->groupsCache[$gname] = $acl;
        if ($old === null) {
            return $this->insertTriple($gname, WIKINI_VOC_ACLS, $acl, GROUP_PREFIX);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        }
        return $this->updateTriple($gname, WIKINI_VOC_ACLS, $old, $acl, GROUP_PREFIX);
    }

    /**
     *
     * @return array The list of all group names
     */
    public function getGroupsList()
    {
        $res = $this->triples->getMatchingTriples(GROUP_PREFIX . '%', WIKINI_VOC_ACLS_URI);
        $prefixLen = strlen(GROUP_PREFIX);
        $list = array();
        foreach ($res as $line) {
            $list[] = substr($line['resource'], $prefixLen);
        }
        return $list;
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return boolean true iff the user is in the given $group
     */
    public function userIsInGroup($group, $user = null, $admincheck = true)
    {
        return $this->checkACL($this->getGroupACL($group), $user, $admincheck);
    }



    public function getPageOwner($tag = "", $time = "")
    {
        if (! $tag = trim($tag)) {
            $tag = $this->getPageTag();
        }
        if ($page = $this->loadPage($tag, $time)) {
            return isset($page["owner"]) ? $page["owner"] : null;
        }
    }

    public function setPageOwner($tag, $user)
    {
        // check if user exists
        if (! $this->loadUser($user)) {
            return;
        }

        // updated latest revision with new owner
        $table = $this->config['table_prefix'] . 'pages';
        $user = $this->database->escapeString($user);
        $tag = $this->database->escapeString($tag);
        $this->database->query(
            "UPDATE $table SET owner = '$user' WHERE tag = '$tag' AND latest = 'Y' LIMIT 1"
        );
    }

    /**
     *
     * @param string $tag
     * @param string $privilege
     * @param boolean $useDefaults
     * @return array [page_tag, privilege, list]
     */
    public function loadAcl($tag, $privilege, $useDefaults = true )
    {
        if (isset($this->aclsCache[$tag][$privilege])) {
            return $this->aclsCache[$tag][$privilege] ;
        }

        $this->aclsCache[$tag] = array();
        if ($useDefaults) {
            $this->aclsCache[$tag] = array(
                'read' => array(
                    'page_tag' => $tag,
                    'privilege' => 'read',
                    'list' => $this->getConfigValue('default_read_acl')
                ),
                'write' => array(
                    'page_tag' => $tag,
                    'privilege' => 'write',
                    'list' => $this->getConfigValue('default_write_acl')
                ),
                'comment' => array(
                    'page_tag' => $tag,
                    'privilege' => 'comment',
                    'list' => $this->getConfigValue('default_comment_acl')
                )
            );
        }

        $table = $this->config['table_prefix'] . 'acls';
        $tag = $this->database->escapeString($tag);
        $res = $this->database->loadAll(
            "SELECT * FROM $table WHERE page_tag = \"$tag\""
        );

        foreach ($res as $acl) {
            $this->aclsCache[$tag][$acl['privilege']] = $acl;
        }

        if (isset($this->aclsCache[$tag][$privilege])) {
            return $this->aclsCache[$tag][$privilege];
        }

        return null ;
    }

    /**
     *
     * @param string $tag the page's tag
     * @param string $privilege the privilege
     * @param string $list the multiline string describing the acl
     */
    public function saveAcl($tag, $privilege, $list, $appendAcl = false )
    {
        $acl = $this->loadAcl($tag, $privilege, false);

        if ($acl and $appendAcl) {
            $list = $acl['list']."\n".$list ;
        }


        $table = $this->config['table_prefix'] . 'acls';
        $tag = $this->database->escapeString($tag);
        $privilege = $this->database->escapeString($privilege);
        $list = $this->database->escapeString(trim(str_replace("\r", '', $list)));

        $sql = "INSERT INTO $table SET list = '$list', page_tag = '$tag', privilege = '$privilege'";
        if ($acl) {
            $sql = "UPDATE $table SET list = '$list' WHERE page_tag = '$tag' AND privilege = '$privilege'";
        }
        $this->database->query($sql);

        // update the acls cache
        $this->aclsCache[$tag][$privilege] = array(
            'page_tag' => $tag,
            'privilege' => $privilege,
            'list' => $list
        );
    }

    /**
     *
     * @param string $tag The page's WikiName
     * @param string|array $privileges A privilege or several privileges to
     *                                 delete from database.
     */
    public function deleteAcl($tag, $privileges = array('read', 'write', 'comment'))
    {
        if (!is_array($privileges)) {
            $privileges = array($privileges);
        }

        // add '"' at begin and end of each escaped privileges elements.
        // TODO utiliser array_walk
        for ($i=0; $i<count($privileges); $i++) {
            $privileges[$i] = '"' . $this->database->escapeString($privileges[$i]) . '"';
        }

        // construct a CSV string with privileges elements
        $privileges = implode(',', $privileges);
        $strTag = $this->database->escapeString($tag);
        $table = $this->config['table_prefix'] . 'acls';
        $this->database->query(
            "DELETE FROM $table
                WHERE page_tag = \"$tag\"
                AND privilege IN ($privileges)"
        );

        if (isset($this->aclsCache[$strTag])) {
            unset($this->aclsCache[$strTag]);
        }

    }

    /**
     * Check if user has a privilege on page.
     * The page's owner has always access (always return true).
     *
     * @param string $privilege The privilege to check (read, write, comment)
     * @param string $tag The page WikiName. Default to current page
     * @param string $user The username. Default to current user.
     * @return boolean true if access granted, false if not.
     */
    public function hasAccess($privilege, $tag = '', $user = '')
    {
        // set default to current page
        if (! $tag = trim($tag)) {
            $tag = $this->getPageTag();
        }
        // set default to current user
        if (!$user) {
            $user = $this->getUserName();
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->userIsOwner($tag)) {
            return true;
        }

        // load acl
        $acl = $this->loadAcl($tag, $privilege);
        // now check them
        $access = $this->checkACL($acl['list'], $user);

        return $access ;
    }

    /**
     * Checks if some $user satisfies the given $acl
     *
     * @param string $acl
     *            The acl to check, in the same format than for pages ACL's
     * @param string $user
     *            The name of the user that must satisfy the ACL. By default
     *            the current remote user.
     * @return bool True if the $user satisfies the $acl, false otherwise
     */
    public function checkACL($acl, $user = null, $admincheck = true)
    {
        if (! $user) {
            $user = $this->getUserName();
        }

        if ($admincheck and $this->userIsAdmin($user)) {
            return true;
        }

        foreach (explode("\n", $acl) as $line) {

            $line = trim($line);

            // check for inversion character "!"
            $negate = false;
            if (preg_match('/^[!](.*)$/', $line, $matches)) {
                $negate = true ;
                $line = $matches[1];
            }

            // if there's still anything left... lines with just a "!" don't count!
            if ($line) {
                switch ($line[0]) {
                    case '#': // comments
                        break;
                    case '*': // everyone
                        return ! $negate;
                        break;
                    case '+': // registered users
                        if (! $this->loadUser($user)) {
                            return $negate;
                        }
                        return !$negate;
                        break;
                    case '@': // groups
                        $gname = substr($line, 1);
                        // paranoiac: avoid line = '@'
                        // we have allready checked if user was an admin
                        if ($gname
                            and $this->userIsInGroup($gname, $user, false)
                        ) {
                            return !$negate;
                        }
                        break;
                    default: // simple user entry
                        if ($line === $user) {
                            return !$negate;
                        }
                }
            }
        }
        // tough luck.
        return false;
    }

    /**
     * Loads the module (handlers) ACL for a certain module.
     *
     * Database example row :
     *  resource = http://www.wikini.net/_vocabulary/handler/addcomment
     *  property = 'http://www.wikini.net/_vocabulary/acls'
     *  value = +
     *
     * @param string $module
     *            The name of the module
     * @param string $module_type
     *            The type of module: 'action' or 'handler'
     * @return string The ACL string  for the given module or "*" if not found.
     */
    public function getModuleACL($module, $module_type)
    {
        $module = strtolower($module);
        switch ($module_type) {

            case 'action':
                if (array_key_exists($module, $this->actionsAclsCache)) {
                    $acl = $this->actionsAclsCache[$module];
                    break;
                }
                $acl = $this->triples->getTripleValue(
                    $module,
                    WIKINI_VOC_ACLS,
                    WIKINI_VOC_ACTIONS_PREFIX
                );
                $this->actionsAclsCache[$module] = $acl;
                if ($acl === null) {
                    $action = $this->getActionObject($module);
                    if (is_object($action)) {
                        $this->actionsAclsCache[$module] = $action->GetDefaultACL();
                        return $this->actionsAclsCache[$module];
                    }
                }
                break;

            case 'handler':
                $acl = $this->triples->getTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_HANDLERS_PREFIX);
                break;
            default:
                return null; // TODO error msg ?
        }
        return $acl === null ? '*' : $acl;
    }

    /**
     * Sets the $acl for a given $module
     *
     * @param string $module
     *            The name of the module
     * @param string $module_type
     *            The type of module ('action' or 'handler')
     * @param string $acl
     *            The new ACL for that module
     * @return 0 on success, > 0 on error (see insertTriple and updateTriple)
     */
    public function setModuleACL($module, $module_type, $acl)
    {
        $module = strtolower($module);
        $voc_prefix = $module_type == 'action' ? WIKINI_VOC_ACTIONS_PREFIX : WIKINI_VOC_HANDLERS_PREFIX;
        $old = $this->triples->getTripleValue($module, WIKINI_VOC_ACLS, $voc_prefix);

        if ($module_type == 'action') {
            $this->actionsAclsCache[$module] = $acl;
        }

        if ($old === null) {
            return $this->triples->insertTriple($module, WIKINI_VOC_ACLS, $acl, $voc_prefix);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        }
        return $this->triples->updateTriple($module, WIKINI_VOC_ACLS, $old, $acl, $voc_prefix);
    }

    /**
     * Checks if a $user satisfies the ACL to access a certain $module
     *
     * @param string $module
     *            The name of the module to access
     * @param string $module_type
     *            The type of the module ('action' or 'handler')
     * @param string $user
     *            The name of the user. By default
     *            the current remote user.
     * @return bool True if the $user has access to the given $module, false otherwise.
     */
    public function checkModuleACL($module, $module_type, $user = null)
    {
        $acl = $this->getModuleACL($module, $module_type);
        if ($acl === null) {
            return true;
        }
        // undefined ACL means everybody has access
        return $this->checkACL($acl, $user);
    }

    // MAINTENANCE
    public function maintenance()
    {
        list ($usec, $sec) = explode(" ", microtime());
        if (!(((float) $usec + (float) $sec) % 9)) {
            // purge referrers
            $this->purgeReferrers();
            // purge old page revisions
            $this->purgePages();
        }
    }

    // THE BIG EVIL NASTY ONE!
    public function run($tag, $method = '')
    {
        // Maintenance une fois sur 10 ??
        $this->maintenance();

        // do our stuff!
        if (! $this->method = trim($method)) {
            $this->method = "show";
        }

        if (! $this->tag = trim($tag)) {
            $this->redirect($this->href("", $this->config['root_page']));
        }

        // TODO vraiment sa place ? constructeur ou plutot controller. ou classe user.
        // WIP Il faut tester si l'utilisateur existe et si le mot de passe est le bon.
        $userFactory = new UserFactory($this->database, $this->cookies);
        $this->connectedUser = $userFactory->getConnected();

        $this->setPage($this->loadPage($tag, (isset($_REQUEST['time']) ? $_REQUEST['time'] : '')));
        $this->logReferrer();

        // correction pour un support plus facile de nouveaux handlers
        $text = _t('HANDLER_NO_ACCESS');
        if ($this->checkModuleACL($this->method, 'handler')) {
            $text = $this->method($this->method);
        }
        echo $text;

        // action redirect: aucune redirection n'a eu lieu, effacer la liste des redirections precedentes
        if (! empty($_SESSION['redirects'])) {
            session_unregister('redirects');
        }
    }
}
