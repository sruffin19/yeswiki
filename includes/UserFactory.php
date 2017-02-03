<?php
namespace YesWiki;
require_once('includes/User.php');
require_once('includes/UnknowUser.php');
require_once('includes/AdminUser.php');
require_once('includes/EncryptedPassword.php');
require_once('includes/ClearPassword.php');


class UserFactory
{
    private $database;
    private $adminGroup;

    public function __construct($database, $adminGroup)
    {
        $this->database = $database;
        $this->adminGroup = $adminGroup;
    }

    /**
     * Créer un utilisateur si il existe dans la base de donnée. Renvoie Faux si
     * il n'existe pas.
     * @param  string $name Nom de l'utilisateur
     * @return User|false
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

        return $this->makeUser($userInfos);
    }

    /**
     * Créé  l'objet User pour l'utilisateur connecté. Si aucune utilisateur
     * n'est connecté renvoie null.
     * @return User|null
     */
    public function getConnected($cookies)
    {
        // If cookies not initialised, no connected user.
        if (is_null($cookies)) {
            return new UnknowUser();
        }

        if ($cookies->isset('name') and isset($_SESSION['user'])) {
            $user = $this->get($cookies->get('name'));
            // User doesn't exist
            if (!$user) {
                return new UnknowUser();
            }
            // bad password.
            $password = new EncryptedPassword($cookies->get('password'));
            if (!$user->password->isMatching($password)) {
                return new UnknowUser();
            }
            return $user;
        }
        return new UnknowUser();
    }

    /**
     * Vérifie si un utilisateur existe.
     * @param  string  $name Nom de l'utilisateur
     * @return boolean vrai si l'utilisateur existe sinon faux
     */
    public function isExist($name)
    {
        if ($this->get($name) === false) {
            return false;
        }
        return true;
    }

    /**
     * Créé un objet User pour chaque utilisateur présent dans la base de données
     * @return array Tableau d'objets User
     */
    public function getAll()
    {
        $tableUsers = $this->database->prefix . 'users';
        $usersInfos = $this->database->loadAll(
            "SELECT * FROM $tableUsers ORDER BY name"
        );

        $users = array();
        foreach ($usersInfos as $userInfos) {
            $users[$userInfos['name']] = $this->makeUser($userInfos);
        }

        return $users;
    }

    /**
     * Créé un nouvel utilisateur
     * @param  string $name     Nom de l'utilisateur (le fait que ce soit un
     *                          WikiName doit deja etre vérifié)
     * @param  string $email    Email de l'utilisateur
     * @param  string $password Mot de passe de l'utilisateur
     * @return User           [description]
     */
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

        return $this->get($name);
    }

    private function makeUser($userInfos)
    {
        $userInfos['password'] = new EncryptedPassword($userInfos['password']);
        $user = new User($this->database, $userInfos);
        if ($this->adminGroup->isMember($user)) {
            $user = new AdminUser($this->database, $userInfos);
        }
        return $user;
    }
}
