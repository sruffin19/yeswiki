<?php
namespace YesWiki;

require_once('includes/Group.php');

class GroupFactory
{
    private $database;
    private $userFactory;

    public function __construct($database, $userFactory)
    {
        $this->database = $database;
        $this->userFactory = $userFactory;
    }

    /**
     * Créé un objet Group pour chaque groupe présent dans la base de données
     * @return array Tableau d'objets Group
     */
    public function getAll()
    {
        $table = $this->database->prefix . 'triples';
        $prefix = GROUP_PREFIX;
        $groupsInfos = $this->database->loadAll(
            "SELECT * FROM $table WHERE resource LIKE '$prefix%'"
        );

        $listGroups = array();
        foreach ($groupsInfos as $groupInfos) {
            $listGroups[] = $this->makeGroup($groupInfos);
        }
        return $listGroups;
    }

    /**
     * Retourne l'objet Group demandé si il existe sinon retourne faux.
     * @param  string $groupName Nom du groupe a retourner.
     * @return Group|false
     */
    public function get($groupName)
    {
        $table = $this->database->prefix . 'triples';
        $prefix = GROUP_PREFIX;
        $groupInfos = $this->database->loadSingle(
            "SELECT * FROM $table WHERE resource = '$prefix$groupName' LIMIT 1"
        );
        if (empty($groupInfos)) {
            return false;
        }
        return $this->makeGroup($groupInfos);
    }

    /**
     * Vérifie si un groupe existe dans la base de donnée.
     * TODO le charger dans le cache
     * @param  [type]  $groupName [description]
     * @return boolean            [description]
     */
    public function isExist($groupName)
    {
        if ($this->get($groupName) === false) {
            return false;
        }
        return true;
    }

    /**
     * Ajoute un nouveau groupe dans la base de donnée. La non existance du
     * groupe doit déjà avoir été testé. Les utilisateurs doivent exister.
     * @param  string $groupName Nom du groupe
     * @param  array  $members   Tableau d'objet User
     * @return Group|bool        Le nouvel objet Group ou false en cas d'échec.
     */
    public function new($groupName, $members)
    {
        $tableTriples = $this->database->prefix . 'triples';
        $resource = $this->database->escapeString(GROUP_PREFIX . $groupName);
        $property = $this->database->escapeString(WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS);
        $membersString = "";
        foreach ($members as $member) {
            $membersString .= $this->database->escapeString($member->name) . "\n";
        }

        $this->database->escapeString($members);

        $sql = "INSERT INTO $tableTriples (resource, property, value)
                    VALUE ('$resource', '$property', '$membersString')";

        if (!$this->database->query($sql)) {
            return false;
        }

        return $this->get($groupName);
    }

    /**
     * Créé un objet groupe a partir des informations stockées dans la base de
     * donnée. (Chaque membre est un objet User)
     * @param  [type] $groupInfos [description]
     * @return [type]             [description]
     */
    private function makeGroup($groupInfos) {
        $prefixLen = strlen(GROUP_PREFIX);
        $members = array();
        foreach (explode("\n", $groupInfos['value']) as $username) {
            $members[$username] = $this->userFactory->get($username);
        }
        return new Group(
            substr($groupInfos['resource'], $prefixLen),
            $members
        );
    }
}
