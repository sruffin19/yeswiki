<?php
namespace YesWiki;

class Group
{
    public $name;
    public $members;

    public function __construct($name, $members)
    {
        $this->members = $members;
        $this->name = $name;
    }

    /**
     * Vérifie si un utilisateur fait partie du groupe.
     * @param  User    $user Objet User pour lequel il faut vérifer
     *                       l'appartenance au groupe.
     * @return boolean       Vrai si l'utilisateur est membre, faux sinon
     */
    public function isMember($user)
    {
        if (in_array($user->name, $this->members)) {
            return true;
        }
        return false;
    }
}
