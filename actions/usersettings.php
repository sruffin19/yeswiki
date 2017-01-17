<?php
/*
usersettings.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002  Patrick PAUL
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
require_once('includes/ClearPassword.php');
use YesWiki\ClearPassword;

if (!isset($_REQUEST["action"])) {
    $_REQUEST["action"] = '';
}

if ($_REQUEST["action"] == "logout") {
    $this->logoutUser();
    $this->setMessage(_t('YOU_ARE_NOW_DISCONNECTED')."&nbsp;!");
    $this->redirect($this->href());
    exit;
}

if (!is_null($this->connectedUser)) {
    switch ($_REQUEST["action"]) {
        case 'update':
            $this->connectedUser->update(
                $_POST["email"],
                $_POST["doubleclickedit"],
                $_POST["show_comments"],
                $_POST["motto"]
            );

            $this->setMessage(_t('PARAMETERS_SAVED')."&nbsp;!");
            $this->redirect($this->href());
            break;

        case 'changepass':
            $oldPassword = new ClearPassword($_POST["oldpass"]);
            $password = new ClearPassword($_POST["password"]);
            if (!$this->connectedUser->password->isMatching($oldPassword)) {
                $error = _t('WRONG_PASSWORD');
                break;
            }

            $error = "";
            if (!$password->isValid($error)) {
                break;
            }

            $this->connectedUser->changePassword($password);

            $this->setMessage(_t('PASSWORD_CHANGED')."&nbsp;!");
            $this->redirect($this->href());
            break;

        default:
            # nothing
            break;
    }
    // user is logged in; display config form
    echo $this->formOpen();
    include('actions/templates/usersettings/update.tpl.html');
    echo $this->formClose();

    echo $this->formOpen();
    include('actions/templates/usersettings/changepass.tpl.html');
    echo $this->formClose();
} else {

    // user is not logged in

    switch ($_REQUEST["action"]) {
        case 'login':
            $userFactory = new YesWiki\UserFactory($this->database);
            $user = $userFactory->get($_POST['name']);
            $password = new ClearPassword($_POST['password']);
            // User doesn't exist
            if ($user === false) {
                // Create user
                $name = trim($_POST["name"]);
                $email = trim($_POST["email"]);
                $password = new ClearPassword($_POST["password"]);
                $confpassword = new ClearPassword($_POST["confpassword"]);

                // check if name is WikkiName style
                if (!$this->isWikiName($name)) {
                    $error = _t('USERNAME_MUST_BE_WIKINAME').".";
                    break;
                }
                if (!$email) {
                    $error = _t('YOU_MUST_SPECIFY_AN_EMAIL').".";
                    break;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = _t('THIS_IS_NOT_A_VALID_EMAIL').".";
                    break;
                }
                if ($confpassword != $password) {
                    $error = _t('PASSWORDS_NOT_IDENTICAL').".";
                    break;
                }
                if (preg_match("/ /", $password)) {
                    $error = _t('NO_SPACES_IN_PASSWORD').".";
                    break;
                }
                if (strlen($password) < 5) {
                    $error = _t('PASSWORD_TOO_SHORT').". "._t('PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM').".";
                    break;
                }

                $user = $userFactory->new($name, $email, $password);
                $this->setUser($user, $_POST["remember"]);
                $this->redirect($this->href('', '', 'action=checklogged', false));
                break;
            }
            // Bad password
            if (!$user->password->isMatching($password)) {
                $error = _t('WRONG_PASSWORD')."&nbsp;!";
                break;
            }

            $this->setUser($user, $_POST["remember"]);
            $this->redirect($this->href('', '', 'action=checklogged', false));
            break;

        case 'checklogged':
            $error = _t('YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED').'.';
            break;

        default:
            // nothing
            break;
    }

    echo $this->formOpen();
    include('actions/templates/usersettings/login.tpl.html');
    echo $this->formClose();
}
// is user trying to log in or register?
/*      $name = trim($_POST["name"]);
        $email = trim($_POST["email"]);
        $password = $_POST["password"];
        $confpassword = $_POST["confpassword"];

        {


            // log in
            $this->setUser($this->loadUser($name));

            // forward
            $this->redirect($this->href());
        }
*/
