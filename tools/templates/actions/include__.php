<?php

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

include_once 'tools/templates/libs/templates.functions.php';

// si la page inclue n'existe pas, on propose de la créer
if (!$incPage = $this->loadPage($incPageName)) {
    // on passe en parametres GET les valeurs du template de la page de provenance
    // pour avoir le même graphisme dans la page créée
    $query_string = 'theme='.urlencode($this->config['favorite_theme']).
        '&amp;squelette='.urlencode($this->config['favorite_squelette']).
        '&amp;style='.urlencode($this->config['favorite_style']);

    $pluginOutputNew = '<div class="'.$class.'">'."\n".
        '<a class="yeswiki-editable" href="'.$this->href('edit', $incPageName, $query_string).'">'.
        '<i class="glyphicon glyphicon-pencil icon-pencil"></i> '._t('TEMPLATE_EDIT').' '.$incPageName.'</a>'."\n".
        '</div>'."\n";
} else {
    // sinon, on remplace les liens vers les NomWikis n'existant pas
    $pluginOutputNew = replace_missingpage_links($pluginOutputNew);
}

// si le lien correspond à l'url, on rajoute une classe "actif"
if (!empty($actif) && $actif == '1') {
    $page_active = $this->tag;
    if (isset($oldpage) && $oldpage != '') {
        // si utilisation de l'extension attach
        $page_active = $oldpage;
    }
    // d'abord les liens avec des attributs class
    $pluginOutputNew = preg_replace(
        '~<a href="'.preg_quote($this->config['base_url'].$page_active).'" class="(.*)"~Ui',
        '<a class="active-link $1" href="'.$this->config['base_url'].$page_active.'"',
        $pluginOutputNew
    );

    // ensuite les liens restants (ceux avec une classe avant ne sont pas pris en compte)
    $pluginOutputNew = str_ireplacement(
        '<a href="'.$this->config['base_url'].$page_active.'"',
        '<a class="active-link" href="'.$this->config['base_url'].$page_active.'"',
        $pluginOutputNew
    );
}

// rajoute le javascript pour le double clic si le parametre est activé et les droits en écriture existent
if (!empty($dblclic) && $dblclic == '1' && $this->hasAccess('write', $incPageName)) {
    $actiondblclic = ' ondblclick="document.location=\''.$this->href('edit', $incPageName).'\';"';
} else {
    $actiondblclic = '';
}
$pluginOutputNew = str_replace('<div class="include ', '<div'.$actiondblclic.' class="', $pluginOutputNew);

// on enleve le préfixe include_ des classes pour que le parametre passé
// et le nom de classe CSS soient bien identiques
$pluginOutputNew = str_replace('include_', '', $pluginOutputNew);

// on ajoute pour le menu du haut la classe nav de bootstrap
if (($incPageName == 'PageMenuHaut' || strstr($class, 'topnavpage')) && !strstr($class, 'horizontal-dropdown-menu')) {
    $pluginOutputNew = preg_replace('/\<ul\>/Ui', '<ul class="nav navbar-nav">', $pluginOutputNew, 1);

    //TODO: a faire pour toutes les pages ou juste le menu???
    if (YW_CHARSET != 'ISO-8859-1' && YW_CHARSET != 'ISO-8859-15') {
        $pluginOutputNew = mb_convert_encoding($pluginOutputNew, 'HTML-ENTITIES', 'UTF-8');
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($pluginOutputNew);
    $xpath = new DOMXpath($dom);

    $dropdowns = $xpath->query('*/div/ul/li/ul');
    if (!is_null($dropdowns)) {
        foreach ($dropdowns as $element) {
            $element->setAttribute('class', 'dropdown-menu');
            $element->parentNode->setAttribute('class', 'dropdown');
        }
    }
    $dropdownslist = $xpath->query('*/div/ul/li/ul/..');
    if (!is_null($dropdownslist)) {
        foreach ($dropdownslist as $element) {
            $nodes = $element->childNodes;
            foreach ($nodes as $node) {
                // we search for #text child or a link, if we accessed the dropdown menu, we break
                if ($node->nodeName == 'ul') {
                    break;
                }

                // we add trigger for dropdown
                if ($node->nodeName == 'a') {
                    $class = $node->getAttribute('class');
                    $node->setAttribute('class', $class.' dropdown-toggle');
                    $node->setAttribute('data-toggle', 'dropdown');
                    $caret = $dom->createElement('b');
                    $caret->setAttribute('class', 'caret');
                    $node->appendChild($caret);
                } elseif ($node->nodeName == '#text' && !trim($node->nodeValue) == '') {
                    // check if <a exists or must be created
                    $a = $dom->createElement('a');
                    $a->setAttribute('class', 'dropdown-toggle');
                    $a->setAttribute('data-toggle', 'dropdown');
                    $a->setAttribute('href', '#');
                    $a->nodeValue = trim($node->nodeValue);
                    $node->nodeValue = '';
                    $caret = $dom->createElement('b');
                    $caret->setAttribute('class', 'caret');
                    $a->appendChild($caret);
                    $node->parentNode->insertBefore($a, $node);
                }
            }
        }
    }

    $activelinks = $xpath->query("//a[contains(@class, 'active-link')]");
    if (!is_null($activelinks)) {
        foreach ($activelinks as $activelink) {
            $class = $activelink->parentNode->getAttribute('class');
            $activelink->parentNode->setAttribute('class', $class.' active');
        }
    }
    $pluginOutputNew = preg_replace(
        '/^<!DOCTYPE.+?>/',
        '',
        str_replace(
            array('<html>', '</html>', '<body>', '</body>'),
            '',
            $dom->saveHTML()
        )
    )."\n";
} elseif (strstr($class, 'menu-unstyled')) {
    // add style to remove bullets on all ul
    $pluginOutputNew = preg_replace('/\<ul\>/Ui', '<ul class="list-unstyled">', $pluginOutputNew);

    // remove list-unstyled class for level 2 ul
    $pluginOutputNew = preg_replace('/\<\/a>\s+<ul class="list-unstyled">/Ui', "</a>\n<ul>", $pluginOutputNew);
}

// on rajoute une div clear pour mettre le flow css en dessous des éléments flottants
$pluginOutputNew = (!empty($clear) && $clear == '1') ?
    $pluginOutputNew.'<div class="clearfix"></div>'."\n" :
    $pluginOutputNew;

$pluginOutputNew = postFormat($pluginOutputNew);
