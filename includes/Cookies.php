<?php
namespace YesWiki;

class Cookies
{
    public $CookiePath = '/';

    public function __construct($baseUrl)
    {
        // determine le chemin pour les cookies
        $parsedUrl = parse_url($baseUrl);
        $this->CookiePath = dirname($parsedUrl['path']);
        // Fixe la gestion des cookie sous les OS utilisant le \ comme separateur de chemin
        $this->CookiePath = str_replace('\\', '/', $this->CookiePath);
        // ajoute un '/' terminal sauf si on est a la racine web
        if ($this->CookiePath != '/') {
            $this->CookiePath .= '/';
        }
    }

    /**
     * [set description]
     * @param string  $name     cookie name
     * @param [type]  $value    cookie value to store
     * @param integer $remember 1 : true ; 0 : false
     * TODO remember a 1 ou 0 c'est vraiment sale. Utiliser un parametre pour la
     * classe ?
     */
    public function set($name, $value, $remember = 0)
    {
        // $remember ne semble pas pris en compte...
        $expire = time() + ($remember ? 90 * 24 * 60 * 60 : 60 * 60);
        setcookie($name, $value, $expire, $this->CookiePath);
    }

    public function del($name)
    {
        setcookie($name, '', 1, $this->CookiePath);
    }

    public function get($name)
    {
        if (!$this->isset($name)) {
            return false;
        }
        return $_COOKIE[$name];
    }

    public function isset($name)
    {
        if (!isset($_COOKIE[$name])) {
            return false;
        }
        return true;
    }
}
