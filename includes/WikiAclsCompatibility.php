<?php
namespace YesWiki;

class WikiAclsCompatibility
{
    public $actionsAclsCache = array();

    /**
     * Caching page ACLs (sql query on yeswiki_acls table).
     * Filled in loadAcl().
     * Updated in saveAcl().
     * @var array
     */
    protected $aclsCache = array() ;

    /**
     *
     * @param string $tag
     * @param string $privilege
     * @param boolean $useDefaults
     * @return array [page_tag, privilege, list]
     */
    public function loadAcl($tag, $privilege, $useDefaults = true )
    {
        if (isset($this->aclsCache[$tag][$privilege])) {
            return $this->aclsCache[$tag][$privilege] ;
        }

        $this->aclsCache[$tag] = array();
        if ($useDefaults) {
            $this->aclsCache[$tag] = array(
                'read' => array(
                    'page_tag' => $tag,
                    'privilege' => 'read',
                    'list' => $this->getConfigValue('default_read_acl')
                ),
                'write' => array(
                    'page_tag' => $tag,
                    'privilege' => 'write',
                    'list' => $this->getConfigValue('default_write_acl')
                ),
                'comment' => array(
                    'page_tag' => $tag,
                    'privilege' => 'comment',
                    'list' => $this->getConfigValue('default_comment_acl')
                )
            );
        }

        $table = $this->config['table_prefix'] . 'acls';
        $tag = $this->database->escapeString($tag);
        $res = $this->database->loadAll(
            "SELECT * FROM $table WHERE page_tag = \"$tag\""
        );

        foreach ($res as $acl) {
            $this->aclsCache[$tag][$acl['privilege']] = $acl;
        }

        if (isset($this->aclsCache[$tag][$privilege])) {
            return $this->aclsCache[$tag][$privilege];
        }

        return null ;
    }

    /**
     *
     * @param string $tag the page's tag
     * @param string $privilege the privilege
     * @param string $list the multiline string describing the acl
     */
    public function saveAcl($tag, $privilege, $list, $appendAcl = false )
    {
        $acl = $this->loadAcl($tag, $privilege, false);

        if ($acl and $appendAcl) {
            $list = $acl['list']."\n".$list ;
        }


        $table = $this->config['table_prefix'] . 'acls';
        $tag = $this->database->escapeString($tag);
        $privilege = $this->database->escapeString($privilege);
        $list = $this->database->escapeString(trim(str_replace("\r", '', $list)));

        $sql = "INSERT INTO $table SET list = '$list', page_tag = '$tag', privilege = '$privilege'";
        if ($acl) {
            $sql = "UPDATE $table SET list = '$list' WHERE page_tag = '$tag' AND privilege = '$privilege'";
        }
        $this->database->query($sql);

        // update the acls cache
        $this->aclsCache[$tag][$privilege] = array(
            'page_tag' => $tag,
            'privilege' => $privilege,
            'list' => $list
        );
    }

    /**
     *
     * @param string $tag The page's WikiName
     * @param string|array $privileges A privilege or several privileges to
     *                                 delete from database.
     */
    public function deleteAcl($tag, $privileges = array('read', 'write', 'comment'))
    {
        if (!is_array($privileges)) {
            $privileges = array($privileges);
        }

        // add '"' at begin and end of each escaped privileges elements.
        // TODO utiliser array_walk
        for ($i=0; $i<count($privileges); $i++) {
            $privileges[$i] = '"' . $this->database->escapeString($privileges[$i]) . '"';
        }

        // construct a CSV string with privileges elements
        $privileges = implode(',', $privileges);
        $strTag = $this->database->escapeString($tag);
        $table = $this->config['table_prefix'] . 'acls';
        $this->database->query(
            "DELETE FROM $table
                WHERE page_tag = \"$tag\"
                AND privilege IN ($privileges)"
        );

        if (isset($this->aclsCache[$strTag])) {
            unset($this->aclsCache[$strTag]);
        }

    }

    /**
     * Check if user has a privilege on page.
     * The page's owner has always access (always return true).
     *
     * @param string $privilege The privilege to check (read, write, comment)
     * @param string $tag The page WikiName. Default to current page
     * @param string $user The username. Default to current user.
     * @return boolean true if access granted, false if not.
     */
    public function hasAccess($privilege, $tag = '', $user = '')
    {
        // set default to current page
        if (! $tag = trim($tag)) {
            $tag = $this->getPageTag();
        }
        // set default to current user
        if (!$user) {
            $user = $this->getUserName();
        }

        // if current user is owner, return true. owner can do anything!
        if ($this->userIsOwner($tag)) {
            return true;
        }

        // load acl
        $acl = $this->loadAcl($tag, $privilege);
        // now check them
        $access = $this->checkACL($acl['list'], $user);

        return $access ;
    }

    /**
     * Checks if some $user satisfies the given $acl
     *
     * @param string $acl
     *            The acl to check, in the same format than for pages ACL's
     * @param string $user
     *            The name of the user that must satisfy the ACL. By default
     *            the current remote user.
     * @return bool True if the $user satisfies the $acl, false otherwise
     */
    public function checkACL($acl, $user = null, $admincheck = true)
    {
        if (! $user) {
            $user = $this->getUserName();
        }

        if ($admincheck and $this->userIsAdmin($user)) {
            return true;
        }

        foreach (explode("\n", $acl) as $line) {

            $line = trim($line);

            // check for inversion character "!"
            $negate = false;
            if (preg_match('/^[!](.*)$/', $line, $matches)) {
                $negate = true ;
                $line = $matches[1];
            }

            // if there's still anything left... lines with just a "!" don't count!
            if ($line) {
                switch ($line[0]) {
                    case '#': // comments
                        break;
                    case '*': // everyone
                        return ! $negate;
                        break;
                    case '+': // registered users
                        if (! $this->loadUser($user)) {
                            return $negate;
                        }
                        return !$negate;
                        break;
                    case '@': // groups
                        $gname = substr($line, 1);
                        // paranoiac: avoid line = '@'
                        // we have allready checked if user was an admin
                        if ($gname
                            and $this->userIsInGroup($gname, $user, false)
                        ) {
                            return !$negate;
                        }
                        break;
                    default: // simple user entry
                        if ($line === $user) {
                            return !$negate;
                        }
                }
            }
        }
        // tough luck.
        return false;
    }

    /**
     * Loads the module (handlers) ACL for a certain module.
     *
     * Database example row :
     *  resource = http://www.wikini.net/_vocabulary/handler/addcomment
     *  property = 'http://www.wikini.net/_vocabulary/acls'
     *  value = +
     *
     * @param string $module
     *            The name of the module
     * @param string $module_type
     *            The type of module: 'action' or 'handler'
     * @return string The ACL string  for the given module or "*" if not found.
     */
    public function getModuleACL($module, $module_type)
    {
        $module = strtolower($module);
        switch ($module_type) {

            case 'action':
                if (array_key_exists($module, $this->actionsAclsCache)) {
                    $acl = $this->actionsAclsCache[$module];
                    break;
                }
                $acl = $this->getTripleValue(
                    $module,
                    WIKINI_VOC_ACLS,
                    WIKINI_VOC_ACTIONS_PREFIX
                );
                $this->actionsAclsCache[$module] = $acl;
                if ($acl === null) {
                    $action = $this->getActionObject($module);
                    if (is_object($action)) {
                        $this->actionsAclsCache[$module] = $action->GetDefaultACL();
                        return $this->actionsAclsCache[$module];
                    }
                }
                break;

            case 'handler':
                $acl = $this->getTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_HANDLERS_PREFIX);
                break;
            default:
                return null; // TODO error msg ?
        }
        return $acl === null ? '*' : $acl;
    }

    /**
     * Sets the $acl for a given $module
     *
     * @param string $module
     *            The name of the module
     * @param string $module_type
     *            The type of module ('action' or 'handler')
     * @param string $acl
     *            The new ACL for that module
     * @return 0 on success, > 0 on error (see insertTriple and updateTriple)
     */
    public function setModuleACL($module, $module_type, $acl)
    {
        $module = strtolower($module);
        $voc_prefix = $module_type == 'action' ? WIKINI_VOC_ACTIONS_PREFIX : WIKINI_VOC_HANDLERS_PREFIX;
        $old = $this->getTripleValue($module, WIKINI_VOC_ACLS, $voc_prefix);

        if ($module_type == 'action') {
            $this->actionsAclsCache[$module] = $acl;
        }

        if ($old === null) {
            return $this->insertTriple($module, WIKINI_VOC_ACLS, $acl, $voc_prefix);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        }
        return $this->updateTriple($module, WIKINI_VOC_ACLS, $old, $acl, $voc_prefix);
    }

    /**
     * Checks if a $user satisfies the ACL to access a certain $module
     *
     * @param string $module
     *            The name of the module to access
     * @param string $module_type
     *            The type of the module ('action' or 'handler')
     * @param string $user
     *            The name of the user. By default
     *            the current remote user.
     * @return bool True if the $user has access to the given $module, false otherwise.
     */
    public function checkModuleACL($module, $module_type, $user = null)
    {
        $acl = $this->getModuleACL($module, $module_type);
        if ($acl === null) {
            return true;
        }
        // undefined ACL means everybody has access
        return $this->checkACL($acl, $user);
    }
}
