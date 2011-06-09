<?php
/**
 * Model for CronsModel
 * 
 * Generated automatically from the mysql_model_gen script.
 * Please update result to your preferences and copy to the final location.
 * 
 * @author Charlie Powell <powellc@powelltechs.com>
 * @date 2011-06-08 20:43:40
 */
class CronsModel extends Model {
	public static $Schema = array(
		'call' => array(
			'type' => Model::ATT_TYPE_STRING,
			'maxlength' => 64,
			'required' => true,
		),
		'frequency' => array(
			'type' => Model::ATT_TYPE_ENUM,
			'options' => array('_disabled','hourly','daily','weekly','monthly'),
		),
		'last_ran' => array(
			'type' => Model::ATT_TYPE_INT,
		),
	);
	
	public static $Indexes = array(
		'primary' => array('call'),
	);

	// @todo Put your code here.

} // END class CronsModel extends Model
