<?php
namespace YesWiki;

require_once('includes/WikiInclusionsCompatibility.php');

class WikiTriplesCompatibilty extends WikiInclusionsCompatibility
{
    protected $triplesCacheByRsrc = array();

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
        $triples = $this->tripleFactory->getByResourceAndProperty(
            $rePrefix . $resource,
            $propPrefix . $property
        );

        // Un peu de mise en forme pour assurer la compatibilité...
        $result = array();
        foreach ($triples as $triple) {
            $result[$triple->resource][$triple->property] = array(
                'id' => $triple->id,
                'value' => $triple->value,
            );
        }
        return $result;
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
        $resource = $rePrefix . $resource;
        $property = $propPrefix . $property;
        $triple = $this->tripleFactory->get($resource, $property, $value);
        if ($triple === false) {
            return 0;
        }
        return $triple->id;
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
        $triple = $this->TripleFactory->new(
            $rePrefix . $resource,
            $propPrefix . $property,
            $value
        );

        if ($triple === false) {
            return 1;
        }

        return 0;
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
        $oldValue,
        $newValue,
        $rePrefix = THISWIKI_PREFIX,
        $propPrefix = WIKINI_VOC_PREFIX
    ) {
        $resource = $rePrefix . $ressource;
        $property = $propPrefix . $property;

        // Vérifie si le nouveau triple n'existe pas déjà.
        $newTriple = $this->tripleFactory->get($resource, $property, $newValue);
        if ($newTriple !== false) {
            return 3;
        }

        // Vérifie si le triple a modifier existe.
        $triple = $this->tripleFactory->get($resource, $property, $oldValue);
        if ($triple === false) {
            return 2;
        }

        if ($triple->set($newValue) === false) {
            return 1;
        }

        return 0;
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
        $resource = $rePrefix . $ressource;
        $property = $propPrefix . $property;

        if (is_null($value)) {
            $triples  = $this->tripleFactory->getByResourceAndProperty(
                $resource,
                $property
            );
            foreach ($triples as $triple) {
                $triple->delete();
            }
            return;
        }

        $triple = $this->tripleFactory->get($resource, $rpoperty, $value);
        if ($triple !== false) {
            $triple->delete();
        }
    }
}
