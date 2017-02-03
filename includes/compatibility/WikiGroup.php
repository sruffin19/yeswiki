<?php
namespace YesWiki\Compatibility;

require_once('includes/compatibility/WikiTriples.php');

class WikiGroup extends WikiTriples
{
    public $groupsCache = array();

    /**
     *
     * @return array The list of all group names
     */
    public function getGroupsList()
    {
        $groups = $this->groupFactory->getAll();
        $list = array();
        foreach ($groups as $group) {
            $list[] = $group->name;
        }
        return $list;
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return boolean true iff the user is in the given $group
     */
    public function userIsInGroup($group, $user = null, $admincheck = true)
    {
        if (is_null($user)) {
            $user = $this->connectedUser;
        } else {
            if (!$this->userFactory->isExist($user)) {
                // L'utilisateur n'existe pas.
                return false;
            }
            // TODO : Attention hack possible si la machine porte le nom d'un
            // utilisateur existant il est possible de se faire passer pour lui.
            $user = $this->userFactory->get($user);
        }

        $group = $this->groupFactory->get($group);
        if ($group->isMember($user)) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return string the ACL associated with the group $gname
     * @see userIsInGroup to check if a user belongs to some group
     */
    public function getGroupACL($group)
    {
        $group = $this->groupFactory->get($group);
        if ($group === false) {
            return "";
        }

        $memberStr = "";
        foreach ($group->members as $member) {
            $memberStr .= $member . "\n";
        }

        return $memberStr;
    }

    /**
     * Créé un nouveau groupe
     *
     * @param string $gname
     *            The name of a group
     * @param string $acl
     *            The new ACL to associate with the group $gname
     * @return int 0 if successful, a triple error code or a specific error code:
     *         1000 if the new value would define the group recursively (no more recursive group)
     *         1001 if $gname is not named with alphanumeric chars
     * @see getGroupACL
     */
    public function setGroupACL($gname, $memberList)
    {
        if (preg_match('/[^A-Za-z0-9]/', $gname)) {
            return 1001;
        }

        $members = array();
        foreach (explode("\n", $memberList) as $user) {
            $members[$user] = $user;
        }

        // le Groupe existe déja.
        if ($this->groupFactory->isExist($gname)) {
            $group = $this->groupFactory->get($gname);
            $group->updateMembers($members);
            return 0;
        }

        // C'est un nouveau groupe
        $group = $this->groupFactory->new($gname, $members);
        return 0;
    }
}
