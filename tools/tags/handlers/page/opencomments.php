<?php
// Verification de securite
if (!defined('WIKINI_VERSION')) {
    die ('acc&egrave;s direct interdit');
}
if (($this->userIsOwner()) || ($this->userIsAdmin()))
{
    // on efface l'existant
    $this->deleteTriple($this->getPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', null, '', '');
    // on ouvre les commentaires
    $this->insertTriple($this->getPageTag(), 'http://outils-reseaux.org/_vocabulary/comments', 1, '', '');
    $this->setMessage(_t('TAGS_COMMENTS_ACTIVATED'));
}
else
{
    $this->setMessage(_t('TAGS_ONLY_FOR_ADMIN_AND_OWNER'));
}

$this->redirect($this->href());
