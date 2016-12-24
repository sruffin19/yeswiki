<?php
/**
 * This class is intended to be extended by each administration action.
 *
 * This will help access rights management. Currently its only particularity is to have a its
 * default ACL set to @admins.
 */
class WikiNiAdminAction extends WikiNiAction {
    function GetDefaultACL()
    {
        return '@'.ADMIN_GROUP;
    }
}
