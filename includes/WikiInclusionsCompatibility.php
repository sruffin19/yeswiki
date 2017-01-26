<?php
namespace YesWiki;

require_once('WikiAclsCompatibility.php');

Class WikiInclusionsCompatibility extends WikiAclsCompatibility
{
    public $inclusions = array();

    // inclusions
    /**
     * Enregistre une nouvelle inclusion dans la pile d'inclusions.
     *
     * @param string $pageTag
     *            Le nom de la page qui va etre inclue
     * @return int Le nombre d'elements dans la pile
     */
    public function register($pageTag)
    {
        return array_unshift($this->inclusions, strtolower(trim($pageTag)));
    }

    /**
     * Retire le dernier element de la pile d'inclusions.
     *
     * @return string Le nom de la page dont l'inclusion devrait se terminer.
     *         null s'il n'y a plus d'inclusion dans la pile.
     */
    public function unregisterLast()
    {
        return array_shift($this->inclusions);
    }

    /**
     *
     * @return array La pile d'inclusions
     *         L'element 0 sera la derniere inclusion, l'element 1 sera son
     *         parent et ainsi de suite.
     */
    public function getAll()
    {
        return $this->inclusions;
    }

    /**
     * Remplace la pile des inclusions par une nouvelle pile (par defaut une pile vide)
     * Permet de formatter une page sans tenir compte des inclusions precedentes.
     *
     * @param array $
     *            La nouvelle pile d'inclusions.
     *            L'element 0 doit representer la derniere inclusion, l'element 1 son parent et ainsi de suite.
     * @return array L'ancienne pile d'inclusions, avec les noms des pages en minuscules.
     */
    public function set($pile = array())
    {
        $temp = $this->inclusions;
        $this->inclusions = $pile;
        return $temp;
    }

    /**
     * Verifie si on est a l'interieur d'une inclusion par $pageTag (sans tenir
     * compte de la casse)
     *
     * @param string $pageTag
     *            Le nom de la page a verifier
     * @return bool True si on est a l'interieur d'une inclusion par $pageTag
     * (false sinon)
     */
    public function isIncludedBy($pageTag)
    {
        return in_array(strtolower($pageTag), $this->inclusions);
    }
}
