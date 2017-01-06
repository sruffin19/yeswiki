<?php
/*
orphanedpages.php

Copyright 2002, 2003  David DELON
Copyright 2002  Charles NEPOTE
Copyright 2002  Patrick PAUL
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$pages = $this->database->loadAll(
    'select distinct tag from '
        . $this->database->prefix
        . 'pages as p left join '
        . $this->database->prefix
        . "links as l on p.tag = l.to_tag "
        . "where l.to_tag is NULL and p.comment_on = '' "
        . "and p.latest = 'Y' order by tag"
);

$text = "<i>" . _t('NO_ORPHAN_PAGES') . "</i>";

if ($pages) {
    $text = "";
    foreach ($pages as $page) {
        $text .= $this->composeLinkToPage($page["tag"], "", "", 0) . "<br />\n";
    }
}
echo $text;
