<?php

/**
 * This class should be extended by each class that reprensents a WikiNi action.
 */
class WikiniAction {
	var $wiki;

	/**
	 * Creates a WikiniAction object associated with the given
	 * wiki object.
	 */
	function __construct(&$wiki)
	{
		$this->wiki = &$wiki;
	}

	/**
	 * Performs an action asked by a user in a wiki page.
	 * @param array $argz An array containing the value of each parameter
	 * given to the action, where the names of the parameters are the key,
	 * corresponding to the given string value.
	 * @param string $command The full command which was in the page
	 * between "{{" and "}}". This allow you to develop actions that do
	 * not use the conventionnal syntax in the 'param="value"' format.
	 * @example if a page contains
	 * 	{{include page="PageTag"}}
	 * $argz will be array('page' => 'PageTag');
	 * $command wil be 'include page="PageTag"'
	 * @return string The result of the action
	 */
	function PerformAction($argz, $command)
	{
		return '';
	}

	/**
	 * @return string The default ACL for this action (usually '*', '+' or '@'.ADMIN_GROUP)
	 */
	function GetDefaultACL()
	{
		return '*';
	}
}
