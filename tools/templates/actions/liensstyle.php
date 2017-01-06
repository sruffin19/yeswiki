<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


// si l'action propose d'autres css à ajouter, on les ajoute
$othercss = $this->getParameter('othercss');
if (!empty($othercss)) {
    echo $this->format('{{linkstyle othercss="'.$othercss.'"}}');
} else {
    echo $this->format('{{linkstyle}}');
}
