<?php
/*
 * $Id: wakka.php 864 2007-11-28 12:44:52Z nepote $
 * Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
 * Copyright 2003 Carlo Zottmann
 * Copyright 2002, 2003, 2005 David DELON
 * Copyright 2002, 2003, 2004, 2006 Charles NEPOTE
 * Copyright 2002, 2003 Patrick PAUL
 * Copyright 2003 Eric DELORD
 * Copyright 2003 Eric FELDSTEIN
 * Copyright 2004-2006 Jean-Christophe ANDRE
 * Copyright 2005-2006 Didier LOISEAU
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 * derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * Yes, most of the formatting used in this file is HORRIBLY BAD STYLE. However,
 * most of the action happens outside of this file, and I really wanted the code
 * to look as small as what it does. Basically. Oh, I just suck. :)
 */

require_once 'includes/constants.php';
require_once 'includes/i18n.inc.php';
require_once 'includes/Wiki.class.php';
require_once 'includes/Configuration.php';
require_once('includes/Plugins.php');

use YesWiki\Configuration;

initI18n();

// stupid version check
if (!isset($_REQUEST)) {
    die(_t('NO_REQUEST_FOUND'));
}

// default configuration values
$wakkaConfig = new Configuration();

// load config
if (!$wakkaConfigfile = GetEnv('WAKKA_CONFIG')) {
    $wakkaConfigfile = 'wakka.config.php';
}

// No configuration file --> start installation process
if (!file_exists($wakkaConfigfile)) {
    if (   ! isset($_REQUEST['installAction'])
        or ! $installAction = trim($_REQUEST['installAction'])
    ) {
        $installAction = "default";
    }
    include 'setup/header.php';
    if (file_exists('setup/' . $installAction . '.php')) {
        include 'setup/' . $installAction . '.php';
    } else {
        echo '<em>', _t("INVALID_ACTION"), '</em>';
    }
    include 'setup/footer.php';
    exit();
}

session_start();
$wakkaConfig->load($wakkaConfigfile);

// give a default timezone to avoid error
// TODO déméler ce truc...
/*if ($wakkaConfig['timezone'] != $wakkaDefaultConfig['timezone']) {
    date_default_timezone_set($wakkaConfig['timezone']);
} elseif (!ini_get('date.timezone')) {
    date_default_timezone_set($wakkaDefaultConfig['timezone']);
}*/


// fetch wakka location TODO vraiment necessaire ? des valeurs par défaut me
// semblent plus adapaté. A confirmer
if (empty($_REQUEST['wiki'])) {
    // redirect to the root page
    header('Location: ' . $wakkaConfig['base_url'] . $wakkaConfig['root_page']);
    exit();
}
$wiki = $_REQUEST['wiki'];

// remove leading slash
$wiki = preg_replace('/^\//', '', $wiki);

// split into page/method, checking wiki name & method name (XSS proof)
if (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`', $wiki, $matches)) {
    list (, $page, $method) = $matches;
} elseif (preg_match('`^' . WN_PAGE_TAG . '$`', $wiki)) {
    $page = $wiki;
} else {
    echo '<p>', _t('INCORRECT_PAGENAME'), '</p>';
    exit();
}

// create wiki object
// and check for database access
try {
    $wiki = new Wiki($wakkaConfig);
} catch (Exception $e) {
    echo '<p>', _t('DB_CONNECT_FAIL'), '</p>';
    trigger_error(_t('LOG_DB_CONNECT_FAIL'));
    exit();
}

// update lang
loadpreferredI18n($page);

// go!
if (!isset($method)) {
    $method = '';
}

// Security (quick hack) : Check method syntax
if (!(preg_match('#^[A-Za-z0-9_]*$#', $method))) {
    $method = '';
}



// Meme nom : remplace
// _Meme nom : avant
// Meme nom : _apres


$plugins_root = 'tools/';

$objPlugins = new plugins($plugins_root);
$objPlugins->getPlugins(true);
$plugins_list = $objPlugins->getPluginsList();

$wakkaConfig['formatter_path'] = 'formatters';
$wikiClasses[] = 'Wiki';
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

for ($iw = 1; $iw < count($wikiClasses); $iw ++) {
    $code = 'Class ' . $wikiClasses[$iw] . ' extends ' . $wikiClasses[$iw - 1] . ' { ' . $wikiClassesContent[$iw] . ' }; ';
    eval($code);

}

// $wiki = new WikiTools($wakkaConfig);
$toEval = '$wiki  = new ' . $wikiClasses[count($wikiClasses) - 1] . '($wakkaConfig);';
eval($toEval);

$wiki->run($page, $method);
