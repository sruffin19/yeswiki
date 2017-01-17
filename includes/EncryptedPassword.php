<?php
namespace YesWiki;

class EncryptedPassword
{
    protected $password;

    public function __construct($password)
    {
        $this->password = $password;
    }

    public function __toString()
    {
        return $this->password;
    }

    protected function encrypt($password)
    {
        return md5($password);
    }

    public function isMatching($password)
    {
        $password = (string)$password;

        if ($this->password === $password) {
            return true;
        }
        return false;
    }
}
