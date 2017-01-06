<?php
// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    die ('acc&egrave;s direct interdit');
}
if (($this->userIsOwner()) || ($this->userIsAdmin()))
{
    //on efface l'existant
    $this->deleteTriple($this->getPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', null, '', '');
    //on ouvre les commentaires
    $this->insertTriple($this->getPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', 0, '', '');
    $this->setMessage("Les commentaires de cette page ont &eacute;t&eacute; d&eacute;sactiv&eacute;s.");
}
else
{
    $this->setMessage("Vous devez &ecirc;tre propri&eacute;taire de la page ou membre du groupe admins pour faire cette op&eacute;ration.");
}

$this->redirect($this->href());
?>
