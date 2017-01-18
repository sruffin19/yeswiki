<?php
namespace YesWiki;

require_once('includes/PageRevision.php');

use \Exception;

class PageFactory
{
    private $database = null;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function getLastRevision($tag)
    {
        $table = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($tag);
        $sql = "SELECT * FROM $table WHERE tag = '$tag' and latest = 'Y' LIMIT 1";
        $pageInfos = $this->database->loadSingle($sql);
        if (empty($pageInfos)) {
            return false;
        }
        return new PageRevision($this->database, $this->digest($pageInfos));
    }

    public function getRevision($tag, $time)
    {
        $table = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($tag);
        $time = $this->database->escapeString($time);
        $sql = "SELECT * FROM $table WHERE tag = '$tag' and time = '$time' LIMIT 1";
        $pageInfos = $this->database->loadSingle($sql);
        if (empty($pageInfos)) {
            return false;
        }
        return new PageRevision($this->database, $this->digest($pageInfos));
    }

    public function getById($id)
    {
        $table = $this->database->prefix . 'pages';
        $pageId = $this->database->escapeString($id);
        $pageInfos = $this->database->loadSingle(
            "SELECT * FROM $table WHERE id = '$pageId' LIMIT 1"
        );
        if (empty($pageInfos)) {
            return false;
        }
        return new PageRevision($this->database, $this->digest($pageInfos));
    }

    public function getAllRevisions($tag)
    {
        $table = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($tag);

        $sql = "SELECT * FROM $table WHERE tag = '$tag'";
        $pagesInfos = $this->database->loadAll($sql);
        if (empty($pagesInfos)) {
            return false;
        }
        return $this->sqlResultsToPages($pagesInfos);
    }

    public function getRecentChanges($limit)
    {
        $table = $this->database->prefix . 'pages';
        $limit = $this->database->escapeString((int)$limit);

        $sql = "SELECT * FROM $table
                    WHERE latest = 'Y' AND comment_on = ''
                    ORDER BY time DESC
                    LIMIT $limit";
        $pagesInfos = $this->database->loadAll($sql);
        return $this->sqlResultsToPages($pagesInfos);
    }

    /**
     * Créé une nouvelle page (pas de version précédente.)
     * @param  string $tag   [description]
     * @param  string $body  [description]
     * @param  User   $owner [description]
     * @param  User   $user  [description]
     * @return [type]        [description]
     */
    public function new($tag, $body, $user = "", $owner = "")
    {
        $tablePages = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($tag);
        $body = $this->database->escapeString($body);
        $user = $this->database->escapeString($user);
        if (get_class($owner) === 'YesWiki\UnknowUser') {
            $owner = "";
        }
        $owner = $this->database->escapeString($owner);
        // add new revision
        $this->database->query(
            "INSERT INTO $tablePages
                SET tag = '$tag',
                    time = now(),
                    owner = '$owner',
                    user = '$user',
                    latest = 'Y',
                    body = '$body',
                    body_r = ''"
        );
        return $this->getLastRevision($tag);
    }

    /**
     * Sauvegarde une nouvelle version d'une page existante
     * @param  PageRevision $page [description]
     * @param  string       $body Contenu de la page
     * @return PageRevision       La nouvelle page
     */
    public function update($page, $body, $user = "")
    {
        if ($page->body === $body) {
            throw new Exception(_t('NO_MODIFICATION'), 1);
        }

        $tablePages = $this->database->prefix . 'pages';
        $tag = $this->database->escapeString($page->tag);
        // set all other revisions to old
        $this->database->query(
            "UPDATE $tablePages SET latest = 'N' WHERE tag = '$tag'"
        );

        $owner = $this->database->escapeString($page->owner);
        $user = $this->database->escapeString($user);
        $body = $this->database->escapeString($body);
        $

        // add new revision
        $this->database->query(
            "INSERT INTO $tablePages
                SET tag = '$tag',
                    time = now(),
                    owner = '$owner',
                    user = '$user',
                    latest = 'Y',
                    body = '$body',
                    body_r = ''"
        );

        return $this->getLastRevision($tag);
    }

    private function digest($infos)
    {
        // the database is in ISO-8859-15, it must be converted
        if (isset($page['body'])) {
            $page['body'] = _convert($page['body'], 'ISO-8859-15');
        }

        $userFactory = new UserFactory($this->database);
        if (isset($page['owner'])) {
            $page['owner'] = $userFactory->get($page['owner']);
        }

        if (isset($page['owner'])) {
            $page['owner'] = $userFactory->get($page['owner']);
        }
        return $infos;
    }

    private function sqlResultsToPages($pagesInfos)
    {
        $pages = array();
        foreach ($pagesInfos as $pageInfos) {
            $pages[] = new PageRevision(
                $this->database,
                $this->digest($pageInfos)
            );
        }
        return $pages;
    }
}
