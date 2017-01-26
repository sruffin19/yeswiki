<?php
namespace YesWiki;

class TripleFactory
{
    protected $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Retourne tous les triples correspondant a la ressource passée en paramètre
     * @param  string $resource ressource a trouver.
     * @return Array of Triple
     */
    public function getAll($resource, $property = "")
    {
        $tableTriples = $this->database->prefix . 'triples';
        $sql = "SELECT * FROM $tableTriples WHERE resource = '$resource'";

        if ($property !== "") {
            $sql .= " AND property = '$property'";
        }

        $triplesInfos = $this->database->loadAll($sql);

        $triples = array();
        foreach ($triplesInfos as $tripleInfos) {
            $triples[] = new Triple(
                $this->databases,
                $tripleInfos['id'],
                $tripleInfos['resource'],
                $tripleInfos['value']
            );
        }
        return $triples;
    }
}
