<?php
namespace YesWiki;

require_once('includes/User.php');

use \Exception;

class UnknowUser extends User
{

    public function __construct()
    {
        $this->name = $_SERVER["REMOTE_ADDR"];
    }

    public function update($email, $doubleClickEdit, $showComments, $motto)
    {
        throw new Exception("Trying to update unconnected user", 1);
    }

    public function changePassword($newPassword)
    {
        throw new Exception("Trying to change password unconnected user", 1);
    }

    public function isReal()
    {
        return false;
    }
}
