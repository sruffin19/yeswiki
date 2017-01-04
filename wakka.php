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
require_once 'includes/urlutils.inc.php';
require_once 'includes/i18n.inc.php';
require_once 'includes/Wiki.class.php';

$t_SQL = 0;

// stupid version check
if (!isset($_REQUEST)) {
    die(_t('NO_REQUEST_FOUND'));
}

// default configuration values
$wakkaConfig = array();
$_rewrite_mode = detectRewriteMode();

$wakkaDefaultConfig = array(
    'wakka_version' => '',
    'wikini_version' => '',
    'yeswiki_version' => '',
    'yeswiki_release' => '',
    'debug' => 'no',
    'mysql_host' => 'localhost',
    'mysql_database' => '',
    'mysql_user' => '',
    'mysql_password' => '',
    'table_prefix' => 'yeswiki_',
    'base_url' => computeBaseURL($_rewrite_mode),
    'rewrite_mode' => $_rewrite_mode,
    'meta_keywords' => '',
    'meta_description' => '',
    'action_path' => 'actions',
    'handler_path' => 'handlers',
    'header_action' => 'header',
    'footer_action' => 'footer',
    'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
    'referrers_purge_time' => 24,
    'pages_purge_time' => 90,
    'default_write_acl' => '*',
    'default_read_acl' => '*',
    'default_comment_acl' => '@admins',
    'preview_before_save' => 0,
    'allow_raw_html' => false,
    'timezone'=>'GMT' // Only used if not set in wakka.config.php nor in php.ini
);
unset($_rewrite_mode);

// load config
if (! $configfile = GetEnv('WAKKA_CONFIG')) {
    $configfile = 'wakka.config.php';
}

if (file_exists($configfile)) {
    include $configfile;
} else {
    // we must init language file without loading the page's settings.. to translate some default config settings
    $wakkaDefaultConfig['root_page'] = _t('HOMEPAGE_WIKINAME');
    $wakkaDefaultConfig['wakka_name'] = _t('MY_YESWIKI_SITE');
}
$wakkaConfigLocation = $configfile;
$wakkaConfig = array_merge($wakkaDefaultConfig, $wakkaConfig);

// give a default timezone to avoid error
if ($wakkaConfig['timezone'] != $wakkaDefaultConfig['timezone']) {
    date_default_timezone_set($wakkaConfig['timezone']);
} elseif (!ini_get('date.timezone')) {
    date_default_timezone_set($wakkaDefaultConfig['timezone']);
}

// compare versions, start installer if necessary
if ($wakkaConfig['wakka_version']
    and (! $wakkaConfig['wikini_version'])
) {
    $wakkaConfig['wikini_version'] = $wakkaConfig['wakka_version'];
}

if (($wakkaConfig['wakka_version'] != WAKKA_VERSION)
    or ($wakkaConfig['wikini_version'] != WIKINI_VERSION)
) {
    // start installer
    if (! isset($_REQUEST['installAction']) or ! $installAction = trim($_REQUEST['installAction'])) {
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

// configuration du cookie de session
// determine le chemin pour les cookies
$a = parse_url($wakkaConfig['base_url']);
$CookiePath = dirname($a['path']);
// Fixe la gestion des cookie sous les OS utilisant le \ comme s?parteur de chemin
$CookiePath = str_replace('\\', '/', $CookiePath);
// ajoute un '/' terminal sauf si on est ? la racine web
if ($CookiePath != '/') {
    $CookiePath .= '/';
}

// start session
session_start();

// fetch wakka location
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
$wiki = new Wiki($wakkaConfig);

// update lang
loadpreferredI18n($page);

// check for database access
if (! $wiki->dblink) {
    echo '<p>', _t('DB_CONNECT_FAIL'), '</p>';
    // Log error (useful to find the buggy server in a load balancing platform)
    trigger_error(_t('LOG_DB_CONNECT_FAIL'));
    exit();
}

// go!
if (!isset($method)) {
    $method = '';
}

// Security (quick hack) : Check method syntax
if (!(preg_match('#^[A-Za-z0-9_]*$#', $method))) {
    $method = '';
}

include 'tools/prepend.php';

$wiki->Run($page, $method);
