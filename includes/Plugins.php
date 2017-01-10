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
        $test = glob($this->location . '*/desc.xml');
        return $res;
    }

    private function getPluginInfo($plugin)
    {
        if (file_exists($plugin)) {
            $this->_current_tag_cdata = '';
            $this->_p_info = array('name' => null, 'version' => null,
                        'active' => null, 'author' => null, 'label' => null,
                        'desc' => null, 'callbacks' => array(), );

            $this->xml = xml_parser_create('ISO-8859-1');
            xml_parser_set_option($this->xml, XML_OPTION_CASE_FOLDING, false);
            xml_set_object($this->xml, $this);
            xml_set_element_handler($this->xml, 'openTag', 'closeTag');
            xml_set_character_data_handler($this->xml, 'cdata');

            xml_parse($this->xml, implode('', file($plugin)));
            xml_parser_free($this->xml);

            if (!empty($this->_p_info['name'])) {
                return $this->_p_info;
            }
            return false;
        }
    }

    private function openTag($plugin, $tag, $attr)
    {
        if ($tag == $this->type && !empty($attr['name'])) {
            $this->_p_info['name'] = $attr['name'];
            $this->_p_info['version'] = (!empty($attr['version'])) ? $attr['version'] : null;
            $this->_p_info['active'] = (!empty($attr['active'])) ? (boolean) $attr['active'] : false;
        }

        if ($tag == 'callback') {
            $this->_p_info['callbacks'][] = array($attr['event'], $attr['function']);
        }
    }

    private function closeTag($plugin, $tag)
    {
        switch ($tag) {
            case 'author':
            case 'label':
            case 'desc':
                $this->_p_info[$tag] = $this->_current_tag_cdata;
                break;
        }
    }

    private function cdata($plugin, $cdata)
    {
        $this->_current_tag_cdata = $cdata;
    }
}
