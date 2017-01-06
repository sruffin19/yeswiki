<?php
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit") ;
}

//parametres wikini
$pagetag = trim($this->getParameter('page')) ;
if (empty($pagetag)) {
    return ('<div class="error_box">Action diaporama : param&ecirc;tre "page" obligatoire.</div>') ;
}

$class = trim($this->getParameter('class')) ;

$template = trim($this->getParameter('template'));
if (empty($template)) {
    $template = 'diaporama_slide.tpl.html';
} elseif ( !file_exists('tools/templates/presentation/templates/'.$template) ) {
    echo ('<div class="error_box">Action diaporama : le param&ecirc;tre "template" pointe sur un fichier inexistant ou illisible. Le template par d&eacute;faut sera utilis&eacute;.</div>') ;
    $template = 'diaporama_slide.tpl.html';
}

//pour l'action diaporama, on simule la presence sur la page, afin qu'il recupere les fichiers attaches au bon endroit
$oldpage = $this->getPageTag();
$this->tag = $pagetag;
$this->page = $this->loadPage($this->tag);

//fonction de generation du diaporama (teste les droits et l'existence de la page)
include_once('tools/templates/libs/templates.functions.php');
echo print_diaporama($pagetag, $template, $class);

//on retablie le bon nom de page
$this->tag = $oldpage;
$this->page = $this->loadPage($oldpage);
