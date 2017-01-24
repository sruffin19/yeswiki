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
        $sql = "SELECT * FROM $table WHERE resource LIKE '$prefix%'";
        $results = $this->database->loadAll($sql);

        $listGroups = array();
        $prefixLen = strlen(GROUP_PREFIX);
        foreach ($results as $groupInfos) {
            $members = array();
            foreach (explode("\n", $groupInfos['value']) as $username) {
                $members[$username] = $this->userFactory->get($username);
            }

            $listGroups[] = new Group(
                substr($groupInfos['resource'], $prefixLen),
                $members
            );

            return $listGroups;
        }
        return $listGroups;
    }
}
