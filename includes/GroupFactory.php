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
