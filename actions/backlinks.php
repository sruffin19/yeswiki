<?php

/*
backlinks.php

Copyright 2002  Patrick PAUL
Copyright 2003  David DELON
Copyright 2003  Charles NEPOTE

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

$targetPage = $this->mainPage;
$title = _t('PAGES_WITH_LINK_TO_CURRENT_PAGE')."&nbsp;: <br />\n";
$tag = trim($this->getParameter('page'));
if (!empty($tag)) {
    $targetPage = $this->pageFactory->getLastRevision($tag);
    $title = _t('PAGES_WITH_LINK')
        . ' '
        . new  YesWiki\Link($targetPage->tag)
        . "&nbsp;: <br />\n";
}

$pages = $targetPage->getLinkingTo();
if (!empty($pages)) {
    echo $title;
    $exclude = explode(';', $this->getParameter('exclude'));
    foreach($exclude as $key => $exclusion){
        $exclude[$key] = trim($exclusion);
    }

    foreach($pages as $page){
        if(!in_array($page['tag'], $exclude)){
            echo new YesWiki\Link($page['tag']) . "<br />\n";
        }
    }
} else {
    echo '<i>'
        . _t('NO_PAGES_WITH_LINK_TO')
        . ' '
        . new  YesWiki\Link($targetPage->tag)
        . '.</i>';
}
