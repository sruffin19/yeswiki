<?php
namespace YesWiki;

require_once('includes/WikiLinkCompatibility.php');
// Classe temporaire pour assurer la compatibilité. A supprimer une fois toutes
// les méthodes remplacées et supprimées.

class WikiUserCompatibility extends WikiLinkCompatibility
{
    /**
     * Charge un utilisateur dans la base de données.
     * @param  string       $name   nom de l'utilisateur
     * @return User|false           Un objet User si l'utilisateur existe sinon
     *                              faux
     */
    public function loadUser($name)
    {
        return $this->userFactory->get($name);
    }

    /**
     * Retourne tous les utilisateurs existant dans la base de donnée.
     * @return array of User
     */
    public function loadUsers()
    {
        return $this->userFactory->getAll();
    }

    /**
     * Renvois l'utilisateur connecté ou une chaine vide.
     * @return [type] [description]
     */
    public function getUser()
    {
        if (is_null($this->connectedUser)) {
            return '';
        }
        return $this->connectedUser->name;
    }

    /**
     * Retourne le nom de l'utilisateur connecté ou l'ip de la machine distante
     * si pas d'utilisateur connecté
     * @return string
     */
    public function getUserName()
    {
        return $this->connectedUser->name;
    }

    /**
     * Connecte un utilisateur
     * @param User  $user       Utilisateur a connecter
     * @param integer $remember Définis si il faut mémoriser un utilisateur sur le
     * long terme. Ne semble pas être utiliser correctement TODO
     */
    public function setUser($user, $remember = 0)
    {
        $this->login($user, $remember = 0);
    }

    /**
     * Déconnecte un utilisateur
     */
    public function logoutUser()
    {
        $this->logout();
    }

    /**
     * Vérifie si l'utilisateur connecté est propriétaire de la page.
     * @param  string $tag Nom de la page.
     * @return bool
     */
    public function userIsOwner($tag = "")
    {
        // check if user is logged in
        if (is_null($this->connectedUser)) {
            return false;
        }
        // set default tag
        if (!$tag = trim($tag)) {
            $tag = $this->GetPageTag();
        }
        // check if user is owner
        if ($this->getPageOwner($tag) == $this->GetUserName()) {
            return true;
        }
        return false;
    }


    /**
     * Vérifie si un utilisateur a les droit d'administrateur. Si aucun
     * utilisateur n'est spécifié c'est l'utilisateur connecté qui est utilisé.
     *
     * @param string $user Le nom de l'utilisateur.
     * @return boolean Vrai si l'utilisateur a les droits.
     */
    public function userIsAdmin($user = null)
    {
        if (is_null($user)) {
            $user = $this->connectedUser;
        }
        return $this->userIsInGroup(ADMIN_GROUP, $user, false);
    }

    public function userWantsComments()
    {
        return ($this->connectedUser->showComments == 'Y');
    }
}
