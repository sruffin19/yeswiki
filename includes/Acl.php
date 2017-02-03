<?php
namespace YesWiki;

class Acl
{
    private $database;
    private $groupFactory;
    private $allowed = array();
    private $unAllowed = array();

    private $everybody = false;
    private $registredUser = false;

    public function __construct($database, $groupFactory, $acl)
    {
        $this->database = $database;
        $this->groupFactory = $groupFactory;
        $this->acl = $acl;
        $this->membersStringToArray($acl);
    }

    /**
     * Retourne l'ACL sous forme de chaine de caractère.
     * @return string
     */
    public function __toString()
    {
        return $this->acl;
    }

    /**
     * Détermine si un utilisateur est autorisé ou interdit par l'ACL
     * @param  strin  $username
     * @return boolean           [description]
     */
    public function isAuthorized($user)
    {
        if ($user->isReal()) {
            // l'utilisateur est administrateur, il peut tout faire.
            if ($user->isAdmin()) {
                return true;
            }
            // L'utilisateur est interdit : il n'a jamais le droit
            if (in_array($user->name, $this->unAllowed)) {
                return false;
            }
        }
        // Tout le monde peut et l'utilisateur n'est pas interdit
        if ($this->everybody) {
            return true;
        }

        if ($user->isReal()) {
            // Seuls les utilisateurs enregistré et non interdit
            if ($this->registredUser) {
                return true;
            }
            // Autorisation spécifique
            if (in_array($user->name, $this->allowed)) {
                return true;
            }
        }
        // Si on arrive la, pas de chance il a pas le droit.
        return false;
    }

    /**
     * Transforme l'ACL sous forme de chaine de caractéres en tableau d'utilisateurs
     * autorisés et interdits. Gère aussi si les utilisateurs enregistrés sont
     * autorisé ou si tout le monde est autorisé.
     * @param  string $aclStr
     */
    private function membersStringToArray($aclStr)
    {
        $acls = explode("\n", $aclStr);

        $this->allowed = array();
        $this->unAllowed = array();

        foreach ($acls as $acl) {

            $acl = trim($acl);

            // Tout le monde.
            if ($acl === '*') {
                $this->everybody = true;
                continue;
            }

            // Les utilisateurs enregistrés
            if ($acl === '+') {
                $this->registredUser = true;
            }

            // C'est un groupe
            if (substr($acl, 0, 1) === '@') {
                $groupName = substr($acl, 1);
                $this->allowed = array_merge(
                    $this->allowed,
                    $this->getGroupMembers($groupName)
                );
                continue;
            }

            if (substr($acl, 0, 1) === '!') {
                // Un groupe de personnes interdites
                if (substr($acl, 1, 1) === '@') {
                    $userName = substr($acl, 2);
                    $this->unAllowed = array_merge(
                        $this->allowed,
                        $this->getGroupMembers($userName)
                    );
                    continue;
                }
                $userName = substr($acl, 1);
                $this->unAllowed[$username] = $username;

                continue;
            }

            $this->allowed[$acl] = $acl;
        }
    }

    private function getGroupMembers($groupName)
    {
        $group = $this->groupFactory->get($groupName);
        if ($group === false) {
            return array();
        }
        return $group->members;
    }
}
