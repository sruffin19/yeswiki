<?php
namespace YesWiki;

Class Link
{
    private $link = "Bad link";
    private $href = "";

    public $internal = false;
    public $tag;
    public $method;
    public $params;

    public function __construct($tag, $method = null, $text = null, $params = null)
    {
        $this->tag = htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET);
        $this->method = htmlspecialchars($method, ENT_COMPAT, YW_CHARSET);
        $this->text = $text;
        $this->params = $params;
        // Ajoute le test sur la chaine vide car certains appels force une
        // chaine vide sur $method
        if (is_null($text) or $text === "") {
            $this->text = htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET);
        }
        if ($this->isInternal()) {
            $this->makeInternal();
            $this->internal = true;
            return;
        }

        if ($this->isEmail()) {
            $this->makeEmail();
            return;
        }

        if ($this->isUrl()) {
            $this->makeUrl();
            return;
        }

        $this->link = htmlspecialchars(
            $tag . ($text ? ' ' . $text : ''),
            ENT_COMPAT,
            YW_CHARSET
        );
    }

    public function __toString()
    {
        return $this->link;
    }

    public function href()
    {
        return $this->href;
    }

    protected function isInternal()
    {
        // Si autre chose que [0-9A-Za-z] alors ce n'est pas un lien interne.
        if (preg_match('/[^[:alnum:]]/', $this->tag)) {
            return false;
        }
        return true;
    }

    protected function isEmail()
    {
        if (filter_var($this->tag, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }
        return false;
    }

    protected function isUrl()
    {
        if (filter_var($this->tag, FILTER_VALIDATE_URL) !== false) {
            return true;
        }
        return false;
    }

    protected function makeInternal()
    {
        // TODO Vérifier l'existance de la page et si elle n'existe pas alors
        // proposer un lien avec le handler edit /!\ pas son rôle. Plutot celui
        // de la classe qui appelle Link. Page ? Necessite un acces a la base de donnée
        // TODO Vérifier si la méthode existe. ?? ou pas. idem remarque au dessus....
        $method = "";
        if ($this->method !== "" and !is_null($this->method)) {
            $method = '/' . $this->method;
        }

        $params = "";
        if ($this->params !== "" and !is_null($this->params)) {
            $params = '&' . $this->params;
        }
        $this->href = "?wiki=$this->tag$method$params";
        $this->link = "<a href='$this->href'>$this->text</a>";
    }

    protected function makeEmail()
    {
        $this->href = "mailto:$this->tag";
        $this->link = "<a href='$this->href'>$this->text</a>";
    }

    protected function makeUrl()
    {
        $this->href = "$this->tag";
        $this->link = "<a href='$this->href'>$this->text</a>";
    }
}
