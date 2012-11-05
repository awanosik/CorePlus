<?php
/**
 * Created by JetBrains PhpStorm.
 * User: powellc
 * Date: 10/18/12
 * Time: 2:01 AM
 * To change this template use File | Settings | File Templates.
 */
class UserSocialHelper {
	public static function ValidateUsername($username, UserUserConfigModel $userconfig){
		// Usernames need to be unique across the system, since they will be used as URL identifiers.

		// However, blank usernames are acceptable.
		if($username == '') return true;

		// Usernames should start with a number at least.
		if(!preg_match('/^[a-zA-Z].*/', $username)) return 'Please start usernames with a letter.';

		// and should not contain spaces
		if(strpos($username, ' ') !== false) return 'Please do not include spaces in your username.';

		// Search the database for the same username.  Remember, THERE CAN ONLY BE ONE!
		$match = UserUserConfigModel::Find(array('key' => 'username', 'value' => $username), 1);
		if($match && $match->get('user_id') != $userconfig->get('user_id')){
			return 'Requested username is already taken!';
		}

		// yay!
		return true;
	}

	/**
	 * Get the resolved profile link for a given user
	 *
	 * @param User $user
	 */
	public static function ResolveProfileLink(User $user){
		if($user->get('username')){
			return Core::ResolveLink('/userprofile/view/' . $user->get('username'));
		}
		else{
			return Core::ResolveLink('/userprofile/view/' . $user->get('id'));
		}
	}

	public static function ResolveProfileLinkById($userid){
		$user = User::Find(array('id' => $userid), 1);
		if(!$user) return false;

		return self::ResolveProfileLink($user);
	}
}