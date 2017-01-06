<?php
if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

// on choisit le template utilisé
$template = $this->getParameter('template'); 
if (empty($template)) {
    $template = 'moteurrecherche_basic.tpl.html';
}

// on peut ajouter des classes à la classe par défaut .searchform
$searchelements['class'] = ($this->getParameter('class') ? 'form-search '.$this->getParameter('class') : 'form-search');
$searchelements['btnclass'] = ($this->getParameter('btnclass') ? ' '.$this->getParameter('btnclass') : '');
$searchelements['iconclass'] = ($this->getParameter('iconclass') ? ' '.$this->getParameter('iconclass') : '');

// on peut changer l'url de recherche
$searchelements['url'] = ($this->getParameter('url') ? $this->getParameter('url') : $this->href("show", "RechercheTexte"));

// si une recherche a été effectuée, on garde les mots clés
$searchelements['phrase'] = (isset($_REQUEST['phrase']) ? $_REQUEST['phrase'] : "");

include_once('includes/tools/squelettephp.class.php');
$searchtemplate = new SquelettePhp('tools/templates/presentation/templates/'.$template);
$searchtemplate->set($searchelements);
echo $searchtemplate->analyser();

?>
