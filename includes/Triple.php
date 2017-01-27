<?php
namespace YesWiki;

class Triple
{
    public $id;
    public $resource;
    public $property;
    public $value;

    protected $database;

    public function __construct($database, $id, $resource, $property, $value)
    {
        $this->id = $id;
        $this->resource = $resource;
        $this->property = $property;
        $this->value = $value;
        $this->database = $database;
    }

    /**
     * Supprime le triple de la base de donnée.
     * @return [type] [description]
     */
    public function delete()
    {
        $tableTriples = $this->database->prefix . 'triples';
        return $this->database->query(
            "DELETE FROM $tableTriples WHERE id = '$this->id'"
        );
    }

    /**
     * Met a jour la valeur du triple
     * @param string $value Nouvelle valeur du triple.
     */
    public function set($value)
    {
        // TODO Vérifier si le triple n'existe pas déjà.
        $tableTriples = $this->database->prefix . 'triples';
        $resource = $this->database->escapeString($this->ressource);
        $property = $this->database->escapeString($this->property);
        $value = $this->databse->escapeString($value);

        $sql = "UPDATE $tableTriples
                    SET value = '$value'
                    WHERE resource = '$resource' AND property = '$property'
                    LIMIT 1";

        if (!$this->database->query($sql)) {
            return false;
        }
        $this->value = $value;
        return true;
    }
}
