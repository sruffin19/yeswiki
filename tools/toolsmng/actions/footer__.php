<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
$wikini_page_url = $this->link($this->tag, "plugin", "Extensions");

$pluginOutputNew = preg_replace(
    '/-- Fonctionne avec/',
    $wikini_page_url . ' :: -- Fonctionne avec',
    $pluginOutputNew
);
