<?php
/*
*/
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// on remplace les liens vers les NomWikis n'existant pas
$pluginOutputNew = replace_missingpage_links($pluginOutputNew);

// on efface des événements javascript issus de wikini
$pluginOutputNew = str_replace('ondblclick="doubleClickEdit(event);"', '', $pluginOutputNew);

// on efface aussi le message sur la non-modification d'une page, car contradictoire avec le changement de theme, et inéfficace pour l'expérience utilisateur
$pluginOutputNew = str_replace('onload="alert(\'Cette page n\\\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\\\'a subi aucune modification.\');"', '', $pluginOutputNew);

if (isset($GLOBALS['template-error']) && $GLOBALS['template-error']['type'] == 'theme-not-found') {
    // on affiche le message d'erreur des templates inexistants
    $pluginOutputNew = str_replace(
        '<div class="page" >',
        '<div class="page">'."\n".'<div class="alert"><a href="#" data-dismiss="alert" class="close">&times;</a><strong>'._t('TEMPLATE_NO_THEME_FILES').' :</strong><br />themes/'.$GLOBALS['template-error']['theme'].'/squelettes/'.$GLOBALS['template-error']['squelette'].'<br />themes/'.$GLOBALS['template-error']['theme'].'/styles/'.$GLOBALS['template-error']['style'].'<br><strong>'._t('TEMPLATE_DEFAULT_THEME_USED').'</strong>.</div>',
        $pluginOutputNew
    );
    $GLOBALS['template-error'] = '';
}

// TODO : make it work with big buffers
//$pluginOutputNew = postFormat($pluginOutputNew);
