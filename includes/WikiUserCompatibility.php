<?php
namespace YesWiki;

// Classe temporaire pour assurer la compatibilité. A supprimer une fois toutes
// les méthodes remplacées et supprimées.

class WikiUserCompatibility
{
    public function loadUser($name)
    {
        $userFactory = new UserFactory($this->database);
        return $userFactory->get($name);
    }

    public function loadUsers()
    {
        $tableUsers = $this->database->prefix . 'users';
        return $this->database->getAll(
            "SELECT * FROM $tableUsers ORDER BY name"
        );
    }

    /**
     * Return name of logged user or '' if no user connected.
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
     * Get connected username, or remote computer name/IP if no user connected.
     * @return string
     */
    public function getUserName()
    {
        if (is_null($this->connectedUser)) {
            return $_SERVER["REMOTE_ADDR"];
        }
        return $this->connectedUser->name;

    }

    public function setUser($user, $remember = 0)
    {
        $_SESSION['user'] = $user->name;
        $this->cookies->set('name', $user->name, $remember);
        $this->cookies->set('password', $user->password, $remember);
        $this->cookies->set('remember', $remember, $remember);
    }

    public function logoutUser()
    {
        $_SESSION['user'] = '';
        $this->cookies->del('name');
        $this->cookies->del('password');
        $this->cookies->del('remember');
    }

    // returns true if logged in user is owner of current page, or page specified in $tag
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
    }


    /**
     * Checks if a given user is andministrator
     *
     * @param string $user
     *            The name of the user (defaults to the current user if not given)
     * @return boolean true iff the user is an administrator
     */
    public function userIsAdmin($user = null)
    {
        return $this->userIsInGroup(ADMIN_GROUP, $user, false);
    }

    /**
     * Check if user has a privilege on page.
     * The page's owner has always access (always return true).
     *
     * @param string $privilege The privilege to check (read, write, comment)
     * @param string $tag The page WikiName. Default to current page
     * @param string $user The username. Default to current user.
     * @return boolean true if access granted, false if not.
     */
    public function hasAccess($privilege, $tag = '', $user = '')
    {
        // set default to current page
        if (! $tag = trim($tag)) {
            $tag = $this->getPageTag();
        }
        // set default to current user
        if (!$user) {
            $user = $this->getUserName();
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->userIsOwner($tag)) {
            return true;
        }

        // load acl
        $acl = $this->loadAcl($tag, $privilege);
        // now check them
        $access = $this->checkACL($acl['list'], $user);

        return $access ;
    }
}
