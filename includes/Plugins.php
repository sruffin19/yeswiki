<?php
class Plugins
{
    public $location;
    public $pluginsList = array();

    public function __construct($location)
    {
        $this->location = null;
        if (is_dir($location)) {
            $this->location = $location.'/';
        }
    }

    /**
     * Get list of all plugins in extension directory
     * @return array|bool Plugin list on success, false on error.
     */
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

    /**
     * Get list of all active plugins in extension directory.
     * @return array|bool Plugin list on success, false on error.
     */
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

    /**
     * Load traduction for plugins.
     * @param  string $lang [description]
     * @return [type]       [description]
     */
    public function loadTraductions($lang = 'fr')
    {
        $pluginsNameList = array_keys($this->pluginsList);
        foreach ($pluginsNameList as $pluginName) {
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

    /**
     * Read desc.xml plugin's file
     * @param  string $plugin   path to plugin's desc.xml file.
     * @return array|bool       Information on success, false on error
     */
    public function getPluginInfo($plugin)
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

    /**
     * List all desc.xml file in sub directory of /tools directory.
     * @return array|bool list of desc.xml file on success, false on error.
     */
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
}
