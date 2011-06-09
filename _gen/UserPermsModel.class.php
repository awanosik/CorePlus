<?php
/**
 * Model for UserPermsModel
 * 
 * Generated automatically from the mysql_model_gen script.
 * Please update result to your preferences and copy to the final location.
 * 
 * @author Charlie Powell <powellc@powelltechs.com>
 * @date 2011-06-08 20:43:40
 */
class UserPermsModel extends Model {
	public static $Schema = array(
		'uid' => array(
			'type' => Model::ATT_TYPE_INT,
			'required' => true,
		),
		'permission' => array(
			'type' => Model::ATT_TYPE_STRING,
			'maxlength' => 32,
			'required' => true,
		),
	);
	
	public static $Indexes = array(
		'primary' => array('uid', 'permission'),
		'pid' => array('permission'),
	);

	// @todo Put your code here.

} // END class UserPermsModel extends Model
