<?php
require_once('includes/WikiActionCompatibility.php');
require_once('includes/Database.php');
require_once('includes/Cookies.php');
require_once('includes/UserFactory.php');
require_once('includes/Link.php');
require_once('includes/PageFactory.php');
require_once('includes/GroupFactory.php');
require_once('includes/TripleFactory.php');

use YesWiki\WikiActionCompatibility;
use YesWiki\Database;
use YesWiki\Cookies;
use YesWiki\UserFactory;
use YesWiki\Link;
use YesWiki\PageFactory;
use YesWiki\GroupFactory;
use YesWiki\TripleFactory;

class Wiki extends WikiActionCompatibility
{
    public $config;
    public $parameter = array();
    public $cookies = null;

    public $connectedUser = null;
    public $mainPage = null;
    public $pageFactory = null;
    public $userFactory = null;
    public $groupFactory = null;

    // constructor
    public function __construct($config)
    {
        $this->config = $config;

        // TODO Utiliser plutôt de l'injection de dépendance
        $this->database = new Database($config);
        $this->cookies = new Cookies($this->config['base_url']);

        $this->groupFactory = new GroupFactory($this->database);

        $adminGroup = $this->groupFactory->get('admins');
        $this->userFactory = new UserFactory($this->database, $adminGroup);

        $this->tripleFactory = new TripleFactory(
            $this->database,
            $this->userFactory
        );

        $this->pageFactory = new PageFactory(
            $this->database,
            $this->config,
            $this->userFactory,
            $this->groupFactory
        );
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
