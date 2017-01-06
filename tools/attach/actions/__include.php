<?php
    if (!defined("WIKINI_VERSION"))
    {
            die ("acc&egrave;s direct interdit");
    }

    $oldpage = $this->getPageTag();
    $this->tag = trim($this->getParameter('page'));
    $this->page = $this->loadPage($this->tag);
?>