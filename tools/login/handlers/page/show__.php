<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
if (!$this->hasAccess('read')) {
    if ($contenu = $this->loadPage("PageLogin")) {
        // si une page PageLogin existe, on l'affiche
        $output = '';
        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->header());
        $output .= $header[0] . "<body>\n<div class=\"container\">\n";
        $output .= $this->format($contenu["body"]).'</div>';
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->footer());
        $pluginOutputNew = $output;
    } else {
        // sinon on affiche le formulaire d'identification minimal
        $pluginOutputNew = str_replace(
            "<i>"._t('LOGIN_NOT_AUTORIZED')."</i>",
            '<div class="alert alert-danger alert-error">'.
            _t('LOGIN_NOT_AUTORIZED').', '._t('LOGIN_PLEASE_REGISTER').'.'.
            '</div>'."\n".
            $this->format('{{login template="minimal.tpl.html"}}'),
            $pluginOutputNew
        );
    }
}
