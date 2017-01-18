<?php
namespace YesWiki;

use \Exception;

class UnknowUser
{
    public $name = null;
    public $password = null;
    public $email = "";
    public $motto = "";
    public $revisionCount = 20;
    public $changesCount = 50;
    public $doubleClickEdit = 'Y';
    public $signupTime = 0;
    public $showComments = 'N';

    public function __construct()
    {
        $this->name = $_SERVER["REMOTE_ADDR"];
    }

    public function __toString()
    {
        return $this->name;
    }

    public function update()
    {
        throw new Exception("Trying to update unconnected user", 1);
    }

    public function changePassword()
    {
        throw new Exception("Trying to change password unconnected user", 1);
    }
}
