<?php
namespace YesWiki;

use \Exception;

class User
{
    public $name = null;
    public $password = null;
    public $email = null;
    public $motto = "";
    public $revisionCount = 20;
    public $changesCount = 50;
    public $doubleClickEdit = 'Y';
    public $signupTime;
    public $showComments;

    private $database;

    public function __toString() {
        return $this->name;
    }

    public function __construct($database, $infos)
    {
        $this->database = $database;
        $this->init($infos);
    }

    /**
     * Met à jour les paramètres de l'utilisateur.
     * @param  string $email           [description]
     * @param  string $doubleClickEdit Y|N
     * @param  styring $showComments   Y|N
     * @param  string $motto           user's motto
     * TODO remonter erreur si echec
     */
    public function update($email, $doubleClickEdit, $showComments, $motto)
    {
        $email = $this->database->escapeString($email);
        $doubleClickEdit = $this->database->escapeString($doubleClickEdit);
        $showComments = $this->database->escapeString($showComments);
        $motto = $this->database->escapeString($motto);
        $name = $this->database->escapeString($this->name);

        $table = $this->database->prefix . 'users';

        $sql = "UPDATE $table
                    SET email = '$email',
                        doubleclickedit = '$doubleClickEdit',
                        show_comments = '$showComments',
                        motto = '$motto'
                    WHERE
                        name = '$name' LIMIT 1";
        $this->database->query($sql);

        $this->email = $email;
        $this->doubleClickEdit = $doubleClickEdit;
        $this->showComments = $showComments;
        $this->motto = $motto;
    }

    /**
     * Change le mot de passe de l'utilisateur.
     * @param  ClearPassword|EncryptedPassword $password Nouveau mot de passe.
     * TODO remonter erreur si echec
     */
    public function changePassword($newPassword)
    {
        $table = $this->database->prefix . 'users';
        $newPassword = $this->database->escapeString((string)$newPassword);
        $name = $this->database->escapeString($this->name);

        $sql = "UPDATE $table SET password = '$newPassword'
                    WHERE name = '$name' LIMIT 1";

        $this->database->query($sql);
    }

    /**
     * Initialise l'utilisateur avec les informations fournies
     * @param  array $infos Tableau des informations.
     */
    private function init($infos) {
        $this->isMinimalInformation($infos);
        $this->name = $infos['name'];
        $this->email = $infos['email'];
        $this->password = $infos['password'];

        if (isset($infos['motto'])) {
            $this->motto = $infos['motto'];
        }

        if (isset($infos['revisioncount'])) {
            $this->revisionCount = $infos['revisioncount'];
        }

        if (isset($infos['changescount'])) {
            $this->changesCount = $infos['changescount'];
        }

        if (isset($infos['doubleclickedit'])) {
            $this->doubleClickEdit = $infos['doubleclickedit'];
        }

        if (isset($infos['signuptime'])) {
            $this->signupTime = $infos['signuptime'];
        }

        if (isset($infos['show_comments'])) {
            $this->showComments = $infos['show_comments'];
        }
    }

    /**
     * Vérifie les informations indispensables sont présentes.
     * @param  array $infos Tableau des informations.
     * @return boolean        true si ça a fonctionné sinon lance une Exception
     */
    private function isMinimalInformation($infos)
    {
        if (!isset($infos['name'])) {
            // TODO créer un message d'erreur multilingue
            throw new Exception("Error Processing Request", 1);
        }

        if (!isset($infos['email'])) {
            throw new Exception(_t('YOU_MUST_SPECIFY_AN_EMAIL'), 1);
        }

        if (!isset($infos['password'])) {
            // TODO créer un message d'erreur multilingue
            throw new Exception("Error Processing Request", 1);
        }
        return true;
    }
}
