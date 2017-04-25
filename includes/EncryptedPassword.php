<?php
namespace YesWiki;

class EncryptedPassword
{
    protected $password;

    public function __construct($password)
    {
        $this->password = $password;
    }

    public function __toString()
    {
        return $this->password;
    }

    /**
     * Vérifie si les deux mot de passe sont identique.
     * @param  EncryptedPassword|PlainPassword  $password Mot de passe a vérifier.
     * @return boolean Vrai si les mots de passe sont identiques.
     */
    public function isMatching($password)
    {
        if ($this->password === $password->password) {
            return true;
        }
        return false;
    }
}
