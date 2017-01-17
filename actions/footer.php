<?php
/*
$Id: footer.php 804 2007-07-26 19:45:21Z lordfarquaad $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003, 2004 David DELON
Copyright 2002, 2003, 2004 Charles NEPOTE
Copyright 2002, 2003  Patrick PAUL
Copyright 2003  Eric DELORD
Copyright 2004  Jean Christophe ANDRE
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
$search_page = $this->href("", "RechercheTexte", "");
$edit_link = $this->hasAccess("write") ? "<a href=\"".$this->href("edit")."\" title=\"Cliquez pour &eacute;diter cette page.\">&Eacute;diter cette page</a> ::\n" : "";
$revisions_link = $this->getPageTime() ? "<a href=\"".$this->href("revisions")."\" title=\"Cliquez pour voir les derni&egrave;res modifications sur cette page.\">".$this->getPageTime()."</a> ::\n" : "";
$owner_info = '';
// if this page exists
if ($this->page)
{
    // if owner is current user
    if ($this->userIsOwner())
    {
        $owner_info = "Propriétaire&nbsp;: vous :: \n";
    }
    else
    {
        if ($owner = $this->getPageOwner())
        {
            $owner_info = "Propriétaire&nbsp;: " . $this->format($owner);
        }
        else
        {
            $owner_info = "Pas de propriétaire ";
            $owner_info .= ($this->getUser() ? "(<a href=\"".$this->href("claim")."\">Appropriation</a>)" : "");
        }
        $owner_info .= " :: \n";
    }
    if ($this->userIsOwner() || $this->userIsAdmin())
    {
        $owner_info .=
        "<a href=\"" . $this->href("acls") . "\" title=\"Cliquez pour éditer les permissions de cette page.\">éditer permissions</a> :: \n" .
        "<a href=\"" . $this->href("deletepage") . "\">Supprimer</a> :: \n";
    }
}
$backlinks = $this->href('backlinks');
include('actions/templates/footer.tpl.html');
