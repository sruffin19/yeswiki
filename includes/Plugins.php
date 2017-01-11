<?php

# ***** BEGIN LICENSE BLOCK *****
# This file is part of DotClear.
# Copyright (c) 2004 Olivier Meunier and contributors. All rights
# reserved.
#
# DotClear is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# DotClear is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with DotClear; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# ***** END LICENSE BLOCK *****

/*
Classe de gestion des plugins et des thémes
*/

class Plugins
{
    public $location;
    public $type;
    public $xml;
    public $pluginsList = array();

    public function __construct($location, $type = 'plugin')
    {
        $this->location = null;
        if (is_dir($location)) {
            $this->location = $location.'/';
        }
        $this->type = $type;
    }

    public function getAllPlugins()
    {
        if (($listFiles = $this->readDir()) !== false) {
            $this->pluginsList = array();
            foreach ($listFiles as $entry => $pluginfile) {
                if (($info = $this->_getPluginInfo($pluginfile)) !== false) {
                    $this->pluginsList[$entry] = $info;
                }
            }
            ksort($this->pluginsList);
            return $this->pluginsList;
        }
        return false;
    }

    public function loadTraductions($lang = 'fr')
    {
        foreach ($this->pluginsList as $pluginName => $v) {
            // language files : first default language, then preferred language
            $langPath = $this->location . $pluginName . '/' . 'lang/' . $pluginName;

            if (file_exists($langPath . '_fr.inc.php')) {
                include($langPath . '_fr.inc.php');
            }

            if ($lang != 'fr'
                and file_exists($langPath . '_' . $lang . '.inc.php')) {
                include($langPath . '_' . $lang . '.inc.php');
            }
        }
    }

    public function getActivePlugins()
    {
        if (($listFiles = $this->readDir()) !== false) {
            $this->pluginsList = array();
            foreach ($listFiles as $entry => $pluginfile) {
                if (($info = $this->getPluginInfo($pluginfile)) !== false) {
                    if ($info['active']) {
                        $this->pluginsList[$entry] = $info;
                    }
                }
            }
            ksort($this->pluginsList);
            return $this->pluginsList;
        }
        return false;
    }

    /* Lecture d'un répertoire é la recherche des desc.xml */
    private function readDir()
    {
        if ($this->location === null) {
            return false;
        }

        $res = array();
        $dir = dir($this->location);
        # Liste du répertoire des plugins
        while (($entry = $dir->read()) !== false) {
            if ($entry != '.'
                and $entry != '..'
                and is_dir($this->location.$entry)
                and file_exists($this->location.$entry . '/desc.xml')
            ) {
                $res[$entry] = $this->location.$entry . '/desc.xml';
            }
        }
        return $res;
    }

    private function getPluginInfo($plugin)
    {
        if (!file_exists($plugin)) {
            return false;
        }

        $xml = simplexml_load_file($plugin);

        $infos = array(
            'author' => (string)$xml->author,
            'label' => (string)$xml->label,
            'desc' => (string)$xml->desc
        );
        foreach ($xml->attributes() as $key => $value) {
            $infos[$key] = (string)$value;
        }

        if (isset($infos['active'])) {
            $infos['active'] = (int)$infos['active'];
        }

        return $infos;
    }
}
