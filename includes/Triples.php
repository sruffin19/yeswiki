<?php
namespace YesWiki;

class Triples
{
    /**
     * A very simple Request level cache for triple resources
     *
     * @var array
     */
    protected $triplesCacheByRsrc = array();

    protected $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Retrieves all the triples that match some criteria.
     * This allows to search triples by their approximate resource or property names.
     * The allowed operators are the sql "LIKE" and the sql "=".
     *
     * Does not use the cache $this->triplesCacheByRsrc.
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve or null
     * @param string $resOp
     *            The operator of comparison between the effective resource and $resource (default: 'LIKE')
     * @param string $propOp
     *            The operator of comparison between the effective property and $property (default: '=')
     * @return array The list of all the triples that match the asked criteria
     */
    public function getMatchingTriples($resource, $property = null)
    {
        $table = $this->database->prefix . 'triples';
        $resource = addslashes($resource);
        $sql = "SELECT * FROM $table WHERE resource LIKE \"$resource\"";

        if ($property !== null) {
            $property = addslashes($property);
            $sql .= " AND property = \"$property\"";
        }
        return $this->loadAll($sql);
    }

    /**
     * Retrieves all the values for a given couple (resource, property)
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve
     * @param string $rePrefix
     *            The prefix to add to $resource (defaults to THISWIKI_PREFIX)
     * @param string $propPrefix
     *            The prefix to add to $property (defaults to WIKINI_VOC_PREFIX)
     * @return array An array of the retrieved values, in the form
     *         array(
     *         0 => array(id = 7 , 'value' => $value1),
     *         1 => array(id = 34, 'value' => $value2),
     *         ...
     *         )
     */
    public function getAllTriplesValues(
        $resource,
        $property,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $res = $rePrefix . $resource ;
        $prop = $propPrefix . $property ;
        if (isset($this->triplesCacheByRsrc[$res])) {
            // All resource's properties was previously loaded.
            //error_log(__METHOD__.' cache hits ['.$res.']['.$prop.'] '. count($this->triplesCacheByRsrc));
            if (isset($this->triplesCacheByRsrc[$res][$prop])) {
                return $this->triplesCacheByRsrc[$res][$prop] ;
            }
            // LoadAll($sql) return an empty array when no result, do the same.
            return array();
        }
        //error_log(__METHOD__.' cache miss ['.$res.']['.$prop.'] '. count($this->triplesCacheByRsrc));
        $this->triplesCacheByRsrc[$res] = array();
        $table = $this->database->prefix . 'triples';
        $slashedRes = addslashes($res);
        $sql = "SELECT * FROM $table WHERE resource = \"$slashedRes\"" ;

        foreach ($this->database->loadAll($sql) as $triple) {
            if (!isset($this->triplesCacheByRsrc[$res][$triple['property']])) {
                $this->triplesCacheByRsrc[$res][$triple['property']] = array();
            }
            $this->triplesCacheByRsrc[$res][ $triple['property'] ][] =
                array( 'id'=>$triple['id'], 'value'=>$triple['value']) ;
        }
        if (isset($this->triplesCacheByRsrc[$res][$prop])) {
            return $this->triplesCacheByRsrc[$res][$prop] ;
        }
        return array() ;
    }

    /**
     * Retrieves a single value for a given couple (resource, property)
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve
     * @param string $rePrefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $propPrefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return string The value corresponding to ($resource, $property) or null if
     *         there is no such couple in the triples table.
     */
    public function getTripleValue(
        $resource,
        $property,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $res = $this->getAllTriplesValues($resource, $property, $rePrefix, $propPrefix);
        if ($res) {
            return $res[0]['value'];
        }

        return null;
    }

    /**
     * Checks whether a triple exists or not
     *
     * @param string $resource
     *            The resource of the triple to find
     * @param string $property
     *            The property of the triple to find
     * @param string $value
     *            The value of the triple to find
     * @param string $rePrefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $propPrefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @param
     *            int The id of the found triple or 0 if there is no such triple.
     */
    public function tripleExists(
        $resource,
        $property,
        $value,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $sql = 'SELECT id FROM ' . $this->database->prefix . 'triples '
            . 'WHERE resource = "' . addslashes($rePrefix . $resource) . '" '
            . 'AND property = "' . addslashes($propPrefix . $property) . '" '
            . 'AND value = "' . addslashes($value) . '"';
        $res = $this->database->loadSingle($sql);
        if (!$res) {
            return 0;
        }
        return $res['id'];
    }

    /**
     * Inserts a new triple ($resource, $property, $value) in the triples' table
     *
     * @param string $resource
     *            The resource of the triple to insert
     * @param string $property
     *            The property of the triple to insert
     * @param string $value
     *            The value of the triple to insert
     * @param string $rePrefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $propPrefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return int An error code: 0 (success), 1 (failure) or 3 (already exists)
     */
    public function insertTriple(
        $resource,
        $property,
        $value,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $res = $rePrefix . $resource ;

        if ($this->tripleExists($res, $property, $value, '', $propPrefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->triplesCacheByRsrc[$res])) {
            unset($this->triplesCacheByRsrc[$res]);
        }


        $sql = 'INSERT INTO ' . $this->database->prefix
            . 'triples (resource, property, value)'
            . 'VALUES ("' . addslashes($res) . '", "' . addslashes($propPrefix . $property)
            . '", "' . addslashes($value) . '")';
        return $this->query($sql) ? 0 : 1;
    }

    /**
     * Updates a triple ($resource, $property, $value) in the triples' table
     *
     * @param string $resource
     *            The resource of the triple to update
     * @param string $property
     *            The property of the triple to update
     * @param string $oldvalue
     *            The old value of the triple to update
     * @param string $newvalue
     *            The new value of the triple to update
     * @param string $rePrefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $propPrefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return int An error code: 0 (succ?s), 1 (?chec),
     *         2 ($resource, $property, $oldvalue does not exist)
     *         or 3 ($resource, $property, $newvalue already exists)
     */
    public function updateTriple(
        $resource,
        $property,
        $oldvalue,
        $newvalue,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $res = $rePrefix . $resource ;

        $tripleId = $this->tripleExists(
            $res,
            $property,
            $oldvalue,
            '',
            $propPrefix
        );

        if (! $tripleId) {
            return 2;
        }

        if ($this->tripleExists($res, $property, $newvalue, '', $propPrefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->triplesCacheByRsrc[$res])) {
            unset($this->triplesCacheByRsrc[$res]);
        }


        $sql = 'UPDATE ' . $this->database->prefix . 'triples '
            . 'SET value = "' . addslashes($newvalue) . '" '
            . 'WHERE id = ' . $tripleId;

        return $this->query($sql) ? 0 : 1;
    }

    /**
     * Deletes a triple ($resource, $property, $value) from the triples' table
     *
     * @param string $resource
     *            The resource of the triple to delete
     * @param string $property
     *            The property of the triple to delete
     * @param string $value
     *            The value of the triple to delete. If set to <tt>null</tt>,
     *            deletes all the triples corresponding to ($resource, $property).
     *            (defaults to <tt>null</tt>)
     * @param string $rePrefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $propPrefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     */
    public function deleteTriple(
        $resource,
        $property,
        $value = null,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $res = $rePrefix . $resource ;

        $sql = 'DELETE FROM ' . $this->database->prefix . 'triples '
            . 'WHERE resource = "' . addslashes($res) . '" '
            . 'AND property = "' . addslashes($propPrefix . $property) . '" ';
        if ($value !== null) {
            $sql .= 'AND value = "' . addslashes($value) . '"';
        }

        // invalidate the cache
        if (isset($this->triplesCacheByRsrc[$res])) {
            unset($this->triplesCacheByRsrc[$res]);
        }

        $this->query($sql);
    }
}
