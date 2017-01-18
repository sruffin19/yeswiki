<?php
namespace YesWiki;

class WikiPageCompatibility
{
    public $page;
    public $tag;

    // VARIABLES
    public function getPageTag()
    {
        return $this->tag;
    }

    public function getPageTime()
    {
        if (isset($this->page['time'])) {
            return $this->page['time'];
        }
        return '';
    }

    public function setPage($page)
    {
        $this->page = $page;
        if (isset($this->page['tag'])) {
            $this->tag = $this->page['tag'];
        }
    }

    // PAGES
    public function loadPage($tag, $time = "")
    {
        if (empty($time)) {
            $page = $this->pageFactory->getLastRevision($tag);
            if ($page === false) {
                return array();
            }
            return $page->array();
        }
        $page = $this->pageFactory->getLastRevisionRevision($tag, $time);
        if ($page === false) {
            return array();
        }
        return $page->array();
    }

    /**
     * Retrieves the cached version of a page.
     *
     * Notice that this method null or false, use
     * $this->getCachedPage($tag) === false
     * to check if a page is not in the cache.
     *
     * @return mixed The cached version of a page:
     *         - the page DB line if the page exists and is in cache
     *         - null if the cache knows that the page does not exists
     *         - false is the cache does not know the page
     */
    public function getCachedPage($tag)
    {
        return $this->loadPage($tag)->array();
    }

    /**
     * Caches a page's DB line.
     *
     * @param array $page
     *            The page (full) DB line or null if the page does not exists
     * @param string $pageTag
     *            The tag of the page to cache. Defaults to $page['tag'] but is
     *            mendatory when $page === null
     */
    public function cachePage($page, $pageTag = null)
    {
        // Rien seulement pour des raisons de compatibilité.
    }

    public function loadPageById($pageId)
    {
        $page = $this->pageFactory->getLastRevisionById($pageId);
        // S'attendent a un tableaux en résultat, (uniquement a des fin de
        // compatibilité : a supprimer dans le futur)
        return $page->array();
    }

    /**
     * Retourne la liste des revisions pour une page.
     * @param  [type] $tag [description]
     * @return [type]       [description]
     */
    public function loadRevisions($tag)
    {
        $pages = $this->pageFactory->getLastRevisionAllRevisions($tag);

        // S'attendent a des tableaux en résultat, (uniquement a des fin de
        // compatibilité : a supprimer dans le futur)
        $result = array();
        foreach ($pages as $page) {
            $result[] = $page->array();
        }
        return $result;
    }

    /**
     * Retourne la liste des pages ayant un lien vers la page passé en
     * paramètre.
     * @param  [type] $tag [description]
     * @return [type]      [description]
     */
    public function loadPagesLinkingTo($tag)
    {
        $this->pageFactory->getLastRevision($tag);
        return $page->linkingTo();
    }

    public function loadRecentlyChanged($limit = 50)
    {
        $limit = (int) $limit;
        $table = $this->database->prefix . 'pages';
        if ($pages = $this->database->loadAll(
            "SELECT id, tag, time, user, owner FROM $table
                WHERE latest = 'Y' AND comment_on = ''
                ORDER BY time DESC LIMIT $limit"
        )) {
            foreach ($pages as $page) {
                $this->cachePage($page);
            }

            return $pages;
        }
    }

    public function getPageCreateTime($tag)
    {
        $this->pageFactory->getLastRevision($tag);
        return $page->time;
    }

    public function isOrphanedPage($tag)
    {
        $this->pageFactory->getLastRevision($tag);
        return $page->isOrphaned();
    }

    public function deleteOrphanedPage($tag)
    {
        $this->pageFactory->getLastRevision($tag);
        $page->delete();
    }

    /**
     * savePage
     * Sauvegarde un contenu dans une page donnee
     *
     * @param string $body
     *            Contenu a sauvegarder dans la page
     * @param string $tag
     *            Nom de la page
     * @param string $commentOn
     *            Indication si c'est un commentaire
     * @param boolean $bypassAcls
     *            Indication si on bypasse les droits d'ecriture
     * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
     *
     */
    public function savePage($tag, $body, $commentOn = "", $bypassAcls = false)
    {
        // TODO Gérer les commentaires quand ils seront refactoré
        $user = $this->connectedUser;

        //if ($bypassAcls
            //or $user->hasAccess($tag, 'write')
            /*or (!empty($commentOn)
                /*and $user->hasAccess($tag, 'comment'))
        //) {*/
            $page = $this->pageFactory->getLastRevision($tag);
            // Nouvelle page
            if($page === false) {
                // TODO créé les ACLS
                $this->pageFactory->new($tag, $body, $user, $user);

                // ACL TODO
                /*$this->saveAcl($tag, 'write', ($commentOn ? $user : $this->getConfigValue('default_write_acl')));
                $this->saveAcl($tag, 'read', $this->getConfigValue('default_read_acl'));
                $this->saveAcl($tag, 'comment', ($commentOn ? '' : $this->getConfigValue('default_comment_acl')));*/

                return true;
            }
            // Page déjà existente.
            $this->pageFactory->update($page, $body, $user);
            return true;
        //}

        return false;
    }

    /**
     * appendContentToPage
     * Ajoute du contenu a la fin d'une page
     *
     * @param string $content
     *            Contenu a ajouter a la page
     * @param string $page
     *            Nom de la page
     * @param boolean $bypassAcls
     *            Bouleen pour savoir s'il faut bypasser les ACLs
     * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
     *
     * TODO : ne sert que dans la fonction logAdministrativeAction, semble
     * inutilement compliqué. a vérifier sans compter le booléen passé en paramêtre
     */
    public function appendContentToPage($content, $page)
    {
        // -- Determine quelle est la page :
        // -- passee en parametre (que se passe-t'il si elle n'existe pas ?)
        // -- ou la page en cours par defaut
        $page = isset($page) ? $page : $this->getPageTag();

        // -- Chargement de la page
        $result = $this->loadPage($page);
        $body = $result['body'];
        // -- Ajout du contenu a la fin de la page
        $body .= $content;

        // -- Sauvegarde de la page
        // TODO : que se passe-t-il si la page est pleine ou si l'utilisateur n'a pas les droits ?
        $this->savePage($page, $body, '', true);

        // now we render it internally so we can write the updated link table.
        $this->clearLinkTable();
        $this->startLinkTracking();
        $temp = $this->setInclusions();
        $this->registerInclusion($this->getPageTag()); // on simule totalement un affichage normal
        $this->format($body);
        $this->setInclusions($temp);
        if (!is_null($this->connectedUser)) {
            $this->trackLinkTo($this->connectedUser->name);
        }
        if ($owner = $this->getPageOwner()) {
            $this->trackLinkTo($owner);
        }
        $this->stopLinkTracking();
        $this->writeLinkTable();
        $this->clearLinkTable();
    }

    /**
     * Make the purge of page versions that are older than the last version
     * older than 3 "pages_purge_time" This method permits to allways keep a
     * version that is older than that period.
     */
    public function purgePages()
    {
        if ($days = $this->getConfigValue('pages_purge_time')) {
            // is purge active ?
            // let's search which pages versions we have to remove
            // this is necessary beacause even MySQL does not handel multi-tables
            // deletes before version 4.0
            $wnPages = $this->getConfigValue('table_prefix') . 'pages';
            $day = addslashes($days);
            $sql = "SELECT DISTINCT a.id FROM $wnPages a, $wnPages b
                        WHERE a.latest = 'N'
                            AND a.time < date_sub(now(), INTERVAL '$day' DAY)
                            AND a.tag = b.tag
                            AND a.time < b.time
                            AND b.time < date_sub(now(), INTERVAL '$day' DAY)";
            $ids = $this->database->loadAll($sql);
            if (count($ids)) {
                // there are some versions to remove from DB
                // let's build one big request, that's better...
                $sql = "DELETE FROM $wnPages WHERE id IN (";
                foreach ($ids as $key => $line) {
                    // NB.: id is an int, no need of quotes
                    $sql .= ($key ? ', ' : '') . $line['id'];
                }
                $sql .= ')';
                // ... and send it !
                $this->database->query($sql);
            }
        }
    }

    public function setPageOwner($tag, $user)
    {
        $userFactory = new UserFactory($this->database, $this->cookies);
        $user = $userFactory->get($user);
        if ($user === false) {
            return;
        }

        $page = $this->pageFactory->getLastRevisionLastRevision($tag);
        if ($page === false) {
            return;
        }
        $page->setOwner($user);
    }
}
