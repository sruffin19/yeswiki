<?php
namespace YesWiki;

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
        // TODO remonter erreur si echec
        $this->database->query($sql);

        $this->email = $email;
        $this->doubleClickEdit = $doubleClickEdit;
        $this->showComments = $showComments;
        $this->motto = $motto;
    }

    public function changePassword($password)
    {
        $table = $this->database->prefix . 'users';
        $password = $this->database->escapeString((string)$password);
        $name = $this->database->escapeString($this->name);

        $sql = "UPDATE $table SET password = '$password'
                    WHERE name = '$name' LIMIT 1";

        $this->database->query($sql);
    }

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
