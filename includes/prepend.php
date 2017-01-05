<?php

// Vérification de sécurité
if (! defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// Meme nom : remplace
// _Meme nom : avant
// Meme nom : _apres

require_once('tools/libs/Plugins.class.php');
require_once('includes/WikiTools.class.php');

$plugins_root = 'tools/';

$objPlugins = new plugins($plugins_root);
$objPlugins->getPlugins(true);
$plugins_list = $objPlugins->getPluginsList();

$wakkaConfig['formatter_path'] = 'formatters';
$wikiClasses[] = 'WikiTools';
$wikiClassesContent[] = '';

foreach ($plugins_list as $pluginName => $v) {

    $pluginBase = $plugins_root . $pluginName . '/';

    if (file_exists($pluginBase . 'wiki.php')) {
        include($pluginBase . 'wiki.php');
    }

    // language files : first default language, then preferred language
    if (file_exists($pluginBase . 'lang/' . $pluginName . '_fr.inc.php')) {
        include($pluginBase . 'lang/' . $pluginName . '_fr.inc.php');
    }

    if ($GLOBALS['prefered_language'] != 'fr'
        and file_exists($pluginBase . 'lang/' . $pluginName . '_' . $GLOBALS['prefered_language'] . '.inc.php')) {
        include($pluginBase . 'lang/' . $pluginName . '_' . $GLOBALS['prefered_language'] . '.inc.php');
    }

    if (file_exists($pluginBase . 'actions')) {
        $wakkaConfig['action_path'] = $pluginBase . 'actions/' . ':' . $wakkaConfig['action_path'];
    }
    if (file_exists($pluginBase . 'handlers')) {
        $wakkaConfig['handler_path'] = $pluginBase . 'handlers/' . ':' . $wakkaConfig['handler_path'];
    }
    if (file_exists($pluginBase . 'formatters')) {
        $wakkaConfig['formatter_path'] = $pluginBase . 'formatters/' . ':' . $wakkaConfig['formatter_path'];
    }
}

for ($iw = 0; $iw < count($wikiClasses); $iw ++) {
    if ($wikiClasses[$iw] != 'WikiTools') {
        $code = 'Class ' . $wikiClasses[$iw] . ' extends ' . $wikiClasses[$iw - 1] . ' { ' . $wikiClassesContent[$iw] . ' }; ';
        eval($code);
    }
}

// $wiki = new WikiTools($wakkaConfig);
$toEval = '$wiki  = new ' . $wikiClasses[count($wikiClasses) - 1] . '($wakkaConfig);';
eval($toEval);
