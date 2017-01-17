<?php
namespace YesWiki;
require_once('includes/User.php');
require_once('includes/EncryptedPassword.php');
require_once('includes/ClearPassword.php');

class UserFactory
{
    private $database;
    private $cookies;

    public function __construct($database, $cookies = null)
    {
        $this->database = $database;
        $this->cookies = $cookies;
    }

    /**
     * return user if exist or false
     * @param  string $name user's name
     * @return User|fasle
     */
    public function get($name)
    {
        $table = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);

        $userInfos = $this->database->loadSingle(
            "SELECT * FROM $table WHERE name = '$name' LIMIT 1"
        );

        if (empty($userInfos)) {
            return false;
        }

        $userInfos['password'] = new EncryptedPassword($userInfos['password']);

        return new User($this->database, $userInfos);
    }

    /**
     * Return connected user (check password) or return null.
     * @return User|null
     */
    public function getConnected()
    {
        // If cookies not initialised, no connected user.
        if (is_null($this->cookies)) {
            return null;
        }

        if ($this->cookies->isset('name') and isset($_SESSION['user'])) {
            $user = $this->get($this->cookies->get('name'));
            // User doesn't exist
            if (!$user) {
                return null;
            }
            // bad password.
            $password = new EncryptedPassword($this->cookies->get('password'));
            if (!$user->password->isMatching($password)) {
                return null;
            }
            return $user;
        }
        return null;
    }

    /**
     * Check if user exist
     * @param  string  $name User's name;
     * @return boolean
     */
    public function isExist($name)
    {
        $table = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);

        $userInfos = $this->database->loadSingle(
            "SELECT * FROM $table WHERE name = '$name' LIMIT 1"
        );

        if (empty($userInfos)) {
            return false;
        }

        return true;
    }

    public function getAll()
    {
        $tableUsers = $this->database->prefix . 'users';
        $usersInfos = $this->database->loadAll(
            "SELECT * FROM $tableUsers ORDER BY name"
        );

        $users = array();
        foreach ($usersInfos as $userInfos) {
            $userInfos['password'] = new EncryptedPassword($userInfos['password']);
            $users[$userInfos['name']] = new User($this->database, $userInfos);
        }

        return $users;
    }

    public function new($name, $email, $password)
    {
        $tableUsers = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);
        $email = $this->database->escapeString($email);
        $password = $this->database->escapeString((string)$password);

        $sql = "INSERT INTO $tableUsers
                SET signuptime = now(),
                    name = '$name',
                    email = '$email',
                    password = md5('$password')";

        $this->database->query($sql);
    }
}
