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
if (!isset($_REQUEST["action"])) $_REQUEST["action"] = '';
if ($_REQUEST["action"] == "logout")
{
    $this->logoutUser();
    $this->setMessage(_t('YOU_ARE_NOW_DISCONNECTED')." !");
    $this->redirect($this->href());
}
else if ($user = $this->getUser())
{

    // is user trying to update?
    if ($_REQUEST["action"] == "update")
    {
        $this->database->query("update ".$this->config["table_prefix"]."users set ".
            "email = '".$this->database->escapeString($_POST["email"])."', ".
            "doubleclickedit = '".$this->database->escapeString($_POST["doubleclickedit"])."', ".
            "show_comments = '".$this->database->escapeString($_POST["show_comments"])."', ".
            "revisioncount = '".$this->database->escapeString($_POST["revisioncount"])."', ".
            "changescount = '".$this->database->escapeString($_POST["changescount"])."', ".
            "motto = '".$this->database->escapeString($_POST["motto"])."' ".
            "where name = '".$user["name"]."' limit 1");

        $this->setUser($this->loadUser($user["name"]));

        // forward
        $this->setMessage(_t('PARAMETERS_SAVED')." !");
        $this->redirect($this->href());
    }

    if ($_REQUEST["action"] == "changepass")
    {
            // check password
            $password = $_POST["password"];
            if (preg_match("/ /", $password)) $error = _t('NO_SPACES_IN_PASSWORD').".";
            else if (strlen($password) < 5) $error = _t('PASSWORD_TOO_SHORT').".";
            else if ($user["password"] != md5($_POST["oldpass"])) $error = _t('WRONG_PASSWORD').".";
            else
            {
                $this->database->query("update ".$this->config["table_prefix"]."users set "."password = md5('".$this->database->escapeString($password)."') "."where name = '".$user["name"]."'");
                $this->setMessage(_t('PASSWORD_CHANGED')." !");
                $user["password"]=md5($password);
                $this->setUser($user);
                $this->redirect($this->href());
            }
    }
    // user is logged in; display config form
    echo $this->formOpen();
    include('actions/templates/usersettings/update.tpl.html');
    echo $this->formClose();

    echo $this->formOpen();
    include('actions/templates/usersettings/changepass.tpl.html');
    echo $this->formClose();

}
else
{
    // user is not logged in

    // is user trying to log in or register?
    if ($_REQUEST["action"] == "login")
    {
        // if user name already exists, check password
        if ($existingUser = $this->loadUser($_POST["name"]))
        {
            // check password
            if ($existingUser["password"] == md5($_POST["password"]))
            {
                $this->setUser($existingUser, $_POST["remember"]);
                $this->redirect($this->href('', '', 'action=checklogged', false));
            }
            else
            {
                $error = _t('WRONG_PASSWORD')."&nbsp;!";
            }
        }
        // otherwise, create new account
        else
        {
            $name = trim($_POST["name"]);
            $email = trim($_POST["email"]);
            $password = $_POST["password"];
            $confpassword = $_POST["confpassword"];

            // check if name is WikkiName style
            if (!$this->isWikiName($name)) $error = _t('USERNAME_MUST_BE_WIKINAME').".";
            else if (!$email) $error = _t('YOU_MUST_SPECIFY_AN_EMAIL').".";
            else if (!preg_match("/^.+?\@.+?\..+$/", $email)) $error = _t('THIS_IS_NOT_A_VALID_EMAIL').".";
            else if ($confpassword != $password) $error = _t('PASSWORDS_NOT_IDENTICAL').".";
            else if (preg_match("/ /", $password)) $error = _t('NO_SPACES_IN_PASSWORD').".";
            else if (strlen($password) < 5) $error = _t('PASSWORD_TOO_SHORT').". "._t('PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM').".";
            else
            {
                $this->database->query("insert into ".$this->config["table_prefix"]."users set ".
                    "signuptime = now(), ".
                    "name = '".$this->database->escapeString($name)."', ".
                    "email = '".$this->database->escapeString($email)."', ".
                    "password = md5('".$this->database->escapeString($_POST["password"])."')");

                // log in
                $this->setUser($this->loadUser($name));

                // forward
                $this->redirect($this->href());
            }
        }
    }
    elseif ($_REQUEST['action'] == 'checklogged')
    {
        $error = _t('YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED').'.';
    }

    echo $this->formOpen();
    include('actions/templates/login.tpl.html');
    echo $this->formClose();
}
