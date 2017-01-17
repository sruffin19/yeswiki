<?php
namespace YesWiki;

Class Link
{
    private $link = "Bad link";

    public function __construct($tag, $method = null, $text = null)
    {
        $this->tag = $tag;
        $this->method = $method;
        $this->text = $text;
        // Ajoute le test sur la chaine vide car certains appels force une
        // chaine vide sur $method
        if (is_null($text) or $text === "") {
            $this->text = htmlspecialchars($tag, ENT_COMPAT, YW_CHARSET);
        }
        if ($this->isInternal()) {
            $this->makeInternal();
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
        // proposer un lien avec le handler edit
        $this->link = "<a href='$this->tag'>$this->text</a>";
    }

    protected function makeEmail()
    {
        $this->link = "<a href='mailto:$this->tag'>$this->text</a>";
    }

    protected function makeUrl()
    {
        $this->link = "<a href='$this->tag'>$this->text</a>";
    }
}
