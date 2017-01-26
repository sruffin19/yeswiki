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

    public function delete()
    {
        $tableTriples = $this->database->prefix . 'triples';
        $this->database->query(
            "DELETE FROM $tableTriples WHERE id = '$this->id'"
        );
    }

    public function update()
    {
        // TODO
    }
}
