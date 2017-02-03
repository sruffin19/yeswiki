<?php
namespace YesWiki;

require_once('includes/Acl.php');
require_once('includes/PageRevision.php');

class PageAcls extends PageRevision
{
    public $defaultAcl = array();

    private $writeAcl = null;
    private $readAcl = null;
    private $commentAcl = null;


    public function __construct(
        $database,
        $config,
        $groupFactory,
        $pageInfos
    ) {
        parent::__construct($database, $pageInfos);
        $this->groupFactory = $groupFactory;
        $this->defaultAcl['read'] = $config->getParameter('default_read_acl');
        $this->defaultAcl['write'] = $config->getParameter('default_write_acl');
        $this->defaultAcl['comment'] = $config->getParameter('default_comment_acl');
    }

    /**
     * Définis la liste des personnes autorisée ou pas à lire dans la page
     * @param string $acl       [description]
     */
    public function updateReadAcl($acl)
    {
        return $this->updateAclInDB('read', $acl);
    }

    /**
     * Définis la liste des personnes autorisée ou pas à écrire dans la page
     * @param [type] $acl       [description]
     */
    public function updateWriteAcl($acl)
    {
        return $this->updateAclInDB('write', $acl);
    }

    /**
     * Définis la liste des personnes autorisée ou pas à commenter dans la page
     * @param [type] $acl       [description]
     */
    public function updateCommentAcl($acl)
    {
        return $this->updateAclInDB('comment', $acl);
    }

    /**
     * Supprime les données de la page dans la base de données.
     * @return [type] [description]
     */
    public function delete()
    {
        parent::delete();
        $this->resetAcl();
    }

    /**
     * Retourne l'ACL lecture de la page. La prend en cache si déjà utilisé
     * sinon la prend dans la base de donnée.
     * @return Acl
     */
    public function getReadAcl(){
        if (is_null($this->readAcl)) {
            $this->readAcl = $this->loadAclFromDB('read');
        }
        return $this->readAcl;
    }

    /**
     * Retourne l'ACL Ecriture de la page. La prend en cache si déjà utilisé
     * sinon la prend dans la base de donnée.
     * @return Acl
     */
    public function getWriteAcl(){
        if (is_null($this->writeAcl)) {
            $this->writeAcl = $this->loadAclFromDB('write');
        }
        return $this->writeAcl;
    }

    /**
     * Retourne l'ACL Commentaire de la page.
     * @return Acl
     */
    public function getCommentAcl(){
        if (is_null($this->commentAcl)) {
            $this->commentAcl = $this->loadAclFromDB('comment');
        }
        return $this->commentAcl;
    }

    /**
     * Remet les acls par défaut sur la page
     */
    public function resetAcl()
    {
        $tableAcls = $this->database->prefix . 'acls';
        $tag = $this->database->escapeString($this->tag);

        $this->database->query(
            "DELETE FROM $tableAcls WHERE page_tag='$tag'"
        );

        $this->writeAcl = null;
        $this->readAcl = null;
        $this->commentAcl = null;
    }

    /**
     * Vérifie si un utilisateur a le droit de lire la page.
     * @param  User $user
     * @return bool
     */
    public function canWrite($user)
    {
        if ($this->isOwner($user)) {
            return true;
        }
        return $this->getWriteAcl()->isAuthorized($user);
    }

    /**
     * Vérifie si un utilisateur a le droit d'écrire dans la page.
     * @param  User $user
     * @return bool
     */
    public function canRead($user)
    {
        if ($this->isOwner($user)) {
            return true;
        }
        return $this->getReadAcl()->isAuthorized($user);
    }

    /**
     * Vérifie si un utilisateur a le droit de commenter la page.
     * @param  User $user
     * @return bool
     */
    public function canComment($user)
    {
        if ($this->isOwner($user)) {
            return true;
        }
        return $this->getCommentAcl()->isAuthorized($user);
    }

    /**
     * Charge une acl depuis la base de donnée
     * @param  string $privilege read|write|comment
     * @return Acl
     */
    protected function loadAclFromDB($privilege)
    {
        $tableAcls = $this->database->prefix . 'acls';
        $pageTag = $this->tag;

        $sql = "SELECT * FROM $tableAcls
                    WHERE page_tag = '$pageTag'
                      AND privilege = '$privilege'
                    LIMIT 1";

        $aclInfos = $this->database->loadSingle($sql);

        $aclList = $this->defaultAcl[$privilege];
        if (!empty($aclInfos)) {
            $aclList = $aclInfos['list'];
        }

        return new Acl(
            $this->database,
            $this->groupFactory,
            $aclList
        );
    }

    /**
     * Enregistre l'ACL dans la base de donnée.
     * @param  string $privilege read|write|comment
     * @param  Acl    $acl
     */
    protected function updateAclInDB($privilege, $acl)
    {
        $tableAcls = $this->database->prefix . 'acls';
        $tag = $this->tag;

        $this->database->query(
            "DELETE FROM $tableAcls
                WHERE page_tag='$tag' AND privilege='$privilege';"
        );
        $this->database->query(
            "INSERT INTO $tableAcls (page_tag, privilege, list)
                VALUES ('$tag', '$privilege', '$acl');"
        );
    }
}
