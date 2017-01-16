<?php
namespace YesWiki;

class WikiUserCompatibility
{
    // USERS
    public function loadUser($name, $password = 0)
    {
        $tableUsers = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);

        $strPassword = "";
        if ($password !== 0) {
            $strPassword = "and password = '"
                . $this->database->escapeString($password)
                . "'";
        }

        return $this->database->loadSingle(
            "SELECT * FROM $tableUsers WHERE name = '$name' $strPassword LIMIT 1"
        );
    }

    public function loadUsers()
    {
        $tableUsers = $this->database->prefix . 'users';
        return $this->database->loadAll(
            "SELECT * FROM $tableUsers ORDER BY name"
        );
    }

    public function getUser()
    {
        return (isset($_SESSION['user']) ? $_SESSION['user'] : '');
    }

    public function getUserName()
    {
        if ($user = $this->GetUser()) {
            $name = $user["name"];
        } else {
            $name = $_SERVER["REMOTE_ADDR"];
        }
        return $name;
    }

    public function setUser($user, $remember = 0)
    {
        $_SESSION['user'] = $user;
        $this->cookies->set('name', $user['name'], $remember);
        $this->cookies->set('password', $user['password'], $remember);
        $this->cookies->set('remember', $remember, $remember);
    }

    public function logoutUser()
    {
        $_SESSION['user'] = '';
        $this->cookies->del('name');
        $this->cookies->del('password');
    }
}
