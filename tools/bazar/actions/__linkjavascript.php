<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

if ($this->getMethod() == "show" || $this->getMethod() == "iframe" || $this->getMethod() == "edit") {
    $this->AddJavascriptFile('tools/bazar/libs/bazar.js');
}
