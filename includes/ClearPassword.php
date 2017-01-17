<?php
namespace YesWiki;

require_once('includes/EncryptedPassword.php');

class ClearPassword extends EncryptedPassword
{
    const MIN_LENGTH = 5;

    private $clearPassword;

    public function __construct($password)
    {
        $this->clearPassword = $password;
        $this->password = $this->encrypt($password);
    }

    /**
     * Vérifie la validité d'un mot de passe.
     * @param  string  $error Contiendra le message d'erreur le cas échéant.
     * @return boolean        true le mot de passe est valide
     *                        false le mot de passe n'est pas valide
     */
    public function isValid(&$error = '')
    {
        if (preg_match("/ /", $this->clearPassword)) {
            $error = _t('NO_SPACES_IN_PASSWORD');
            return false;
        }

        if (strlen($this->clearPassword) < self::MIN_LENGTH) {
            $error = _t('PASSWORD_TOO_SHORT');
            return false;
        }
        return true;
    }
}
