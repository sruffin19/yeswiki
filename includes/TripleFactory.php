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
     * Retourne tous les triples correspondant a la ressource et a la propriété
     * passées en paramètre
     * @param  string $resource ressource a trouver.
     * @return Array of Triple
     */
    public function getByResourceAndProperty($resource, $property)
    {
        $tableTriples = $this->database->prefix . 'triples';
        $sql = "SELECT * FROM $tableTriples
                    WHERE resource = '$resource'
                      AND property = '$property'";

        $triplesInfos = $this->database->loadAll($sql);

        $triples = array();
        foreach ($triplesInfos as $tripleInfos) {
            $triples[] = $this->makeTripleFromDbInfos($tripleInfos);
        }
        return $triples;
    }

    /**
     * Retourne tous les triples correspondant a la ressource
     * passée en paramètre
     * @param  string $resource ressource a trouver.
     * @return Array of Triple
     */
    public function getByResource($resource)
    {
        $tableTriples = $this->database->prefix . 'triples';
        $sql = "SELECT * FROM $tableTriples
                    WHERE resource = '$resource'";

        $triplesInfos = $this->database->loadAll($sql);

        $triples = array();
        foreach ($triplesInfos as $tripleInfos) {
            $triples[] = $this->makeTripleFromDbInfos($tripleInfos);
        }
        return $triples;
    }

    /**
     * Retourne tous les triples correspondant a la propriété
     * passée en paramètre
     * @param  string $resource ressource a trouver.
     * @return Array of Triple
     */
    public function getByProperty($property)
    {
        $tableTriples = $this->database->prefix . 'triples';
        $sql = "SELECT * FROM $tableTriples
                    WHERE property = '$property'";

        $triplesInfos = $this->database->loadAll($sql);

        $triples = array();
        foreach ($triplesInfos as $tripleInfos) {
            $triples[] = $this->makeTripleFromDbInfos($tripleInfos);
        }
        return $triples;
    }

    /**
     * Retourne le triple correspondant au trio ressource, propriété, valeur.
     * Retourne faux si le triple n'existe pas
     * @param  string $resource
     * @param  string $property
     * @param  string $value
     * @return Triple|bool
     */
    public function get($resource, $property, $value)
    {
        $table = $this->database->prefix . 'triples';
        $tripleInfos = $this->database->loadSingle(
            "SELECT * FROM $table
                WHERE resource = '$resource'
                  AND property = '$property'
                  AND value = '$value'
                  LIMIT 1"
        );
        if (empty($tripleInfos)) {
            return false;
        }
        return $this->makeTripleFromDbInfos($tripleInfos);
    }

    /**
     * Vérifie si un triple existe déjà dans la base de donnée.
     * @param  string  $resource
     * @param  string  $property
     * @param  string  $value
     * @return boolean
     */
    public function isExist($resource, $property, $value)
    {
        $triples = $this->get($ressource, $property, $value);

        foreach ($triples as $triple) {
            if ($triple->value === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Créé un nouveau triple et le retourne. Retourne faux en cas d'erreur.
     * @param  [type] $resource 
     * @param  [type] $property
     * @param  [type] $value
     * @return [type]
     */
    public function new($resource, $property, $value)
    {
        if ($this->isExist($resource, $property, $value)) {
            return $this->get($resource, $property, $value);
        }

        $tableTriples = $this->database->prefix . 'triples';
        $sql = "INSERT INTO $tableTriples (resource, property, value)
                    VALUE ('$resource', '$property', '$value')";

        if (!$this->database->query($sql)) {
            return false;
        }
        return $this->get($resource, $property, $value);
    }

    private function makeTripleFromDbInfos($tripleInfos)
    {
        return new Triple(
            $this->databases,
            $tripleInfos['id'],
            $tripleInfos['resource'],
            $tripleInfos['property'],
            $tripleInfos['value']
        );
    }
}
