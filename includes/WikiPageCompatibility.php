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
        $page = $this->pageFactory->getById($pageId);
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
        $pages = $this->pageFactory->getAllRevisions($tag);

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

    public function setPageOwner($tag, $user)
    {
        $user = $this->userFactory->get($user);
        if ($user === false) {
            return;
        }

        $page = $this->pageFactory->getLastRevision($tag);
        if ($page === false) {
            return;
        }
        $page->setOwner($user);
    }
}
