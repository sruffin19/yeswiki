<?php
namespace YesWiki;

class Configuration implements \ArrayAccess
{
    private $parameters;

    private $rewriteMode = null;

    public function __construct()
    {
        $this->parameters = array(
            'wakka_version' => '',
            'wikini_version' => '',
            'yeswiki_version' => '',
            'yeswiki_release' => '',
            'mysql_host' => 'localhost',
            'mysql_database' => '',
            'mysql_user' => '',
            'mysql_password' => '',
            'table_prefix' => 'yeswiki_',
            'base_url' => $this->computeBaseURL(),
            'rewrite_mode' => $this->isRewriteMode(),
            'meta_keywords' => '',
            'meta_description' => '',
            'action_path' => 'actions',
            'handler_path' => 'handlers',
            'formatter_path' => 'formatters',
            'header_action' => 'header',
            'footer_action' => 'footer',
            'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
            'referrers_purge_time' => 24,
            'pages_purge_time' => 90,
            'default_write_acl' => '*',
            'default_read_acl' => '*',
            'default_comment_acl' => '@admins',
            'preview_before_save' => 0,
            'allow_raw_html' => false,
            'timezone' => 'GMT', // Only used if not set in wakka.config.php nor in php.ini
            'root_page' => _t('HOMEPAGE_WIKINAME'),
            'wakka_name' => _t('MY_YESWIKI_SITE'),
        );
    }

    /**
     * ArrayAccess
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->parameters[] = $value;
            return;
        }
        $this->parameters[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->parameters[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->parameters[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->parameters[$offset]) ? $this->parameters[$offset] : null;
    }

    public function load($filename)
    {
        include($filename);
        //overwrite old values with new.
        $this->parameters = array_merge($this->parameters, $wakkaConfig);
    }

    public function getParameter($paramName)
    {
        if (array_key_exists($paramName, $this->parameters)) {
            return $this->parameters[$paramName];
        }
        return null;
    }

     /**
     * Automatically detects the rewrite mode
     * @return boolean True if the rewrite mode has been detected as activated,
     * false otherwise.
     */
    private function isRewriteMode()
    {
        if (is_null($this->rewriteMode)) {
            $pieces = parse_url($_SERVER['REQUEST_URI']);
            $scriptName = substr($pieces['path'], - strlen(WAKKA_ENGINE));
            if ($scriptName != WAKKA_ENGINE) {
                $this->rewriteMode = true;
            }
            $this->rewriteMode = false;
        }
        return $this->rewriteMode;
    }

    /**
     * Computes the base url of the wiki, used as default configuration value.
     * This function works with https sites two.
     * @param boolean $rewrite_mode Indicates whether the rewrite mode is activated
     * as it affects the resulting url. Defaults to false.
     * @return string The base url of the wiki
     */
    private function computeBaseURL()
    {
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS'])) {
            $protocol = 'https://';
        }

        $urlPieces = parse_url($_SERVER['REQUEST_URI']);

        $port = '';
        if ($_SERVER["SERVER_PORT"] != 80
            and $_SERVER["SERVER_PORT"] != 443) {
            $port = ':' . $_SERVER["SERVER_PORT"];
        }

        $urlParam = '';
        if (!$this->isRewriteMode()) {
            $urlParam = '?wiki=';
        }

        return $protocol
            . $_SERVER["HTTP_HOST"]
            . $port
            . $urlPieces['path']
            . $urlParam;
    }
}
