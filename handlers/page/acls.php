<?php
/*
$Id: acls.php 833 2007-08-10 01:16:57Z gandon $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright  2003  Eric FELDSTEIN
Copyright  2003  Jean-Pascal MILCENT
Copyright  2004  Jean Christophe ANDRé
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

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
    die ("acc&egrave;s direct interdit");
}

ob_start();

?>
<div class="page">
<?php

if ($this->page && ($this->userIsOwner() || $this->userIsAdmin()))
{
    if ($_POST)
    {
        // store lists
        $this->saveAcl($this->getPageTag(), "read", $_POST["read_acl"]);
        $this->saveAcl($this->getPageTag(), "write", $_POST["write_acl"]);
        $this->saveAcl($this->getPageTag(), "comment", ($this->page['comment_on'] ? "" : $_POST["comment_acl"]));
        $message = "Droits d\'acc&egrave;s mis &agrave; jour ";//$message = "Access control lists updated";
        
        // change owner?
        if ($newowner = $_POST["newowner"])
        {
            $this->setPageOwner($this->getPageTag(), $newowner);
            $message .= " et changement du propri&eacute;taire. Nouveau propri&eacute;taire : ".$newowner;//$message .= " and gave ownership to ".$newowner;
        }

        // redirect back to page
        $this->setMessage($message."!");
        $this->redirect($this->href());
    }
    else
    {
        // load acls
        $readACL = $this->loadAcl($this->getPageTag(), "read");
        $writeACL = $this->loadAcl($this->getPageTag(), "write");
        $commentACL = $this->loadAcl($this->getPageTag(), "comment");

        // show form
        ?>
        <h3>Liste des droits d'acc&egrave;s de la page  <?php echo  $this->composeLinkToPage($this->getPageTag()) ?></h3><!-- Access Control Lists for-->
        <br />
        
        <?php echo  $this->formOpen("acls") ?>
        <table border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td valign="top" style="padding-right: 20px">
                    <b>Droits de lecture :</b><br /><!-- Read ACL:-->
                    <textarea name="read_acl" rows="4" cols="20"><?php echo  $readACL["list"] ?></textarea>
                </td>
                <td valign="top" style="padding-right: 20px">
                    <b>Droits d'&eacute;criture :</b><br /><!-- Write ACL:-->
                    <textarea name="write_acl" rows="4" cols="20"><?php echo  $writeACL["list"] ?></textarea>
                </td>
<?php if (!$this->page['comment_on']) { ?>
                <td valign="top" style="padding-right: 20px">
                    <b>Droits des commentaires :</b><br /><!-- Comments ACL:-->
                    <textarea name="comment_acl" rows="4" cols="20"><?php echo  $commentACL["list"] ?></textarea>
                </td>
<?php } ?>
            </tr>
            <tr>
                <td colspan="3">
                    <b>Changer le propri&eacute;taire :</b><br /><!-- Set Owner:-->
                    <select name="newowner">
                        <option value="">Ne rien modifier</option><!-- Don't change-->
                        <option value="">&nbsp;</option>
                        <?php
                        if ($users = $this->loadUsers())
                        {
                            foreach($users as $user)
                            {
                                echo "<option value=\"",htmlspecialchars($user["name"], ENT_COMPAT, YW_CHARSET),"\">",$user["name"],"</option>\n";
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <br />
                    <input type="submit" value="Enregistrer" style="width: 120px" accesskey="s" /><!-- Store ACLs-->
                    <input type="button" value="Annuler" onclick="history.back();" style="width: 120px" /><!-- Cancel -->
                </td>
            </tr>
        </table>
        <?php
        echo$this->formClose();
    }
}
else
{
    echo"<i>Vous ne pouvez pas g&eacute;rer les permissions de cette page.</i>";
}

?>
</div>
<?php

$content = ob_get_clean();
echo $this->header();
echo $content;
echo $this->footer();

?>
