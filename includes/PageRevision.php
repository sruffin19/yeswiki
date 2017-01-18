<?php
namespace YesWiki;

class PageRevision
{
    /**
     * [$infos description]
     * @var array Avec les champs :
     */
    private $database;

    public $id;
    public $tag;
    public $time;
    public $body;
    public $owner;
    public $user;
    public $latest;
    public $handler;
    public $commentOn;

    private $orphaned = null;

    public function __construct($database, $pageInfos)
    {
        $this->database = $database;
        $this->init($pageInfos);
    }

    public function getCreateTime()
    {
        if (!isset($this->infos['time'])) {
            return false;
        }
        return $this->infos['time'];
    }

    public function isOrphaned()
    {
        if (is_null($this->orphaned)) {
            $this->loadOrphanedStatus();
        }
        return $this->orphaned;
    }

    /**
     * Supprime une page.
     * @return [type] [description]
     */
    public function delete()
    {
        $tablePages = $this->database->prefix . 'pages';
        $tableLinks = $this->database->prefix . 'links';
        $tableAcls = $this->database->prefix . 'acls';
        $tableReferrers = $this->database->prefix . 'referrers';
        $tag = $this->database->escapeString($this->tag);

        $this->database->query(
            "DELETE FROM $tablePages WHERE tag='$tag' OR comment_on='$tag'"
        );
        $this->database->query(
            "DELETE FROM $tableLinks WHERE from_tag='$tag'"
        );
        $this->database->query(
            "DELETE FROM $tableAcls WHERE page_tag='$tag'"
        );
        $this->database->query(
            "DELETE FROM $tableReferrers WHERE page_tag='$tag'"
        );
    }

    /**
     * Retourne la liste des pages pointant vers cette page. (uniquement le Nom
     * des pages.)
     * @return array [description]
     */
    public function getLinkingTo()
    {
        $table = $this->database->prefix . 'links';
        $tag = $this->database->escapeString($this->tag);
        return $this->database->loadAll(
            "SELECT from_tag AS tag FROM $table
                WHERE to_tag = '$tag' ORDER BY tag"
        );
    }

    /**
     * [setOwner description]
     * @param [type] $user [description]
     */
    public function setOwner($user)
    {
        // updated latest revision with new owner
        $table = $this->database->prefix . 'pages';
        $user = $this->database->escapeString($user->name);
        $tag = $this->database->escapeString($tag);
        $this->database->query(
            "UPDATE $table SET owner = '$user' WHERE id = '$this->id' LIMIT 1"
        );
    }

    // Méthode temporaire pour la compatibilité
    public function array()
    {
        return array(
            'id' => $this->id,
            'tag' => $this->tag,
            'time' => $this->time,
            'body' => $this->body,
            'owner' => $this->owner,
            'user' => $this->user,
            'latest' => $this->latest,
            'handler' => $this->handler,
            'comment_on' => $this->commentOn,
        );

    }

    private function loadOrphanedStatus()
    {
        $tablePages = $this->database->prefix . 'pages';
        $tableLinks = $this->database->prefix . 'links';
        $tag = $this->database->escapeString($this->infos['tag']);

        if (empty(
            $this->database->loadAll(
                "SELECT DISTINCT tag FROM $tablePages
                        AS p LEFT JOIN $tableLinks
                        AS l ON p.tag = l.to_tag
                    WHERE l.to_tag IS NULL AND p.latest = 'Y'
                        AND tag = '$tag'"
            )
        )) {
            $this->orphaned = true;
        }
        $this->orphaned =  false;
    }

    /**
     * Initialise l'utilisateur avec les informations fournies
     * @param  array $infos Tableau des informations.
     */
    private function init($infos) {
        $this->id = $infos['id'];
        $this->tag = $infos['tag'];
        $this->time = $infos['time'];
        $this->body = $infos['body'];
        $this->owner = $infos['owner'];
        $this->user = $infos['user'];
        $this->handler = $infos['handler'];
        $this->latest = $infos['latest'];
        $this->commentOn = $infos['comment_on'];
    }
}
