<?php
namespace YesWiki;

require_once('includes/User.php');

use \Exception;

class AdminUser extends User
{
    public function isAdmin()
    {
        return true;
    }
}
