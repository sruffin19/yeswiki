<?php
require_once('includes/Action.php');
require_once('includes/Database.php');
require_once('includes/Triples.php');
require_once('includes/Cookies.php');
require_once('includes/Inclusions.php');
require_once('includes/UserFactory.php');
require_once('includes/Link.php');
require_once('includes/PageFactory.php');

use YesWiki\Database;
use YesWiki\Triples;
use YesWiki\Cookies;
use YesWiki\Inclusions;
use YesWiki\Actions;
use YesWiki\UserFactory;
use YesWiki\Link;
use YesWiki\PageFactory;

class Wiki extends Actions
{
    public $config;
    public $inclusions;
    public $parameter = array();
    public $queryLog = array();
    public $triples = null;
    public $cookies = null;

    public $connectedUser = null;
    public $mainPage = null;
    public $pageFactory = null;
    public $userFactory = null;

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
        $this->database = new Database($config);
        $this->triples = new Triples($this->database);
        $this->cookies = new Cookies($this->config['base_url']);
        $this->inclusions = new Inclusions();
        $this->pageFactory = new PageFactory($this->database);
        $this->userFactory = new UserFactory($this->database, $this->cookies);
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
        return $this->action($action);
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
        return $this->action($action);
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
        $handler = 'page';
        if (!empty($this->page) and isset($this->page['handler'])) {
            $handler = $this->page['handler'];
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

    // THE BIG EVIL NASTY ONE!
    public function run($tag, $method = '')
    {
        // do our stuff!
        $this->method = "show";
        $method = trim($method);
        if (!empty($method)) {
            $this->method = $method;
        }

        $tag = trim($tag);
        if (empty($tag)) {
            $this->redirect($this->href("", $this->config['root_page']));
        }
        $this->tag = $tag;

        // TODO vraiment sa place ? constructeur ou plutot controller. ou
        // classe user.
        $this->connectedUser = $this->userFactory->getConnected($this->cookies);
        $this->mainPage = false;
        $this->page = array();
        if (isset($_REQUEST['time'])) {
            $this->mainPage = $this->pageFactory->getRevision(
                $this->tag,
                $_REQUEST['time']
            );
        }
        if (!isset($_REQUEST['time'])) {
            $this->mainPage = $this->pageFactory->getLastRevision($this->tag);
        }

        if ($this->mainPage !== false) {
            $this->page = $this->mainPage->array();
        }

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

    /**
     * Connecte un utilisateur
     * @param  User  $user       Utilisateur a connecter.
     * @param  integer $remember [description]
     */
    public function login($user, $remember = 0)
    {
        // TODO check credential
        $_SESSION['user'] = $user->name;
        $this->cookies->set('name', $user->name, $remember);
        $this->cookies->set('password', $user->password, $remember);
        $this->cookies->set('remember', $remember, $remember);
    }

    /**
     * Déconnecte l'utilisateur courant
     */
    public function logout() {
        unset($this->connectedUser);
        $this->connectedUser = null;
        $_SESSION['user'] = '';
        $this->cookies->del('name');
        $this->cookies->del('password');
        $this->cookies->del('remember');
    }
}
