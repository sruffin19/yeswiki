<?php
namespace YesWiki;

class Group
{
    public $name;
    public $members;

    public function __construct($database, $name, $members)
    {
        $this->members = $members;
        $this->name = $name;
        $this->database = $database;
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

    /**
     * Mets à jour la liste des membres avec la nouvelle liste.
     * @param  [type] $memberList [description]
     * @return [type]             [description]
     */
    public function updateMembers($memberList)
    {
        $tableTriples = $this->database->prefix . 'triples';
        $resource = $this->database->escapeString(GROUP_PREFIX . $this->name);
        $membersString = "";
        foreach ($members as $member) {
            $membersString .= $this->database->escapeString($member->name) . "\n";
        }

        $sql = "UPDATE $tableTriples
                    SET value = '$membersString',
                    WHERE resource = '$resource'
                    LIMIT 1";

        if (!$this->database->query($sql)) {
            return false;
        }
        return true;
    }
}
