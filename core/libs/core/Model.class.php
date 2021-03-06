<?php
/**
 * Core model systems, including Model, ModelException, ModelFactory, and ModelValidationException.
 *
 * This file makes up a major component of the underlying datastore system of Core+.  It offers rather high
 * level of accessing and manipulating data in the datastore, essentially providing as the entirity of the
 * "M" part in MVC.
 *
 * In order to build custom models in this system, they must all extend the Model class.  By doing such,
 * they inherit this class's core abilities and functionality essential for operation, not to mention
 * they are picked up as valid Models in the core.
 *
 * @package Core Plus\Core
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2009-2012  Charlie Powell
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */

/**
 * Core Model object, main class responsible for the "M" component in MVC.
 *
 * Every Model in the system be it core models or user-created components, MUST extend this
 * class in order for proper functioning.
 */
class Model implements ArrayAccess {

	/**
	 * Short set of characters, usually < 256 in length.
	 */
	const ATT_TYPE_STRING = 'string';
	/**
	 * Any length of string, (up to approximately 64kb or so of text)
	 */
	const ATT_TYPE_TEXT = 'text';
	/**
	 * Any binary-safe data.
	 */
	const ATT_TYPE_DATA = 'data';
	/**
	 * Integer only, no precision
	 */
	const ATT_TYPE_INT = 'int';
	/**
	 * Number with decimal places.
	 */
	const ATT_TYPE_FLOAT = 'float';
	/**
	 * True or false value, approximate values are translated appropriately, (such as "yes" or "0").
	 */
	const ATT_TYPE_BOOL = 'boolean';
	/**
	 * Value from a set of options
	 */
	const ATT_TYPE_ENUM = 'enum';
	/**
	 * This is a unique id for a table, but instead of relying on auto-increment, it's based on Core's GenerateUUID method.
	 */
	const ATT_TYPE_UUID = '__uuid';
	/**
	 * The auto-incrementing integer of a table
	 */
	const ATT_TYPE_ID = '__id';
	/**
	 * Date this record was last updated, updated automatically
	 */
	const ATT_TYPE_UPDATED = '__updated';
	/**
	 * Date this record was originally created, set automatically
	 */
	const ATT_TYPE_CREATED = '__created';
	/**
	 * Site ID for the current site.
	 * Has no function outside of enterprise/multisite mode.
	 */
	const ATT_TYPE_SITE = '__site';

	/**
	 * Mysql datetime and ISO 8601 formatted datetime field.
	 * This format is discouraged in Core+, but allowed.
	 *
	 * This stores the data as 'YYYY-mm-dd HH:ii:ss'.
	 */
	const ATT_TYPE_ISO_8601_DATETIME = 'ISO_8601_datetime';

	/**
	 * Mysql-specific timestamp field.
	 * This stores the data as 'YYYY-mm-dd HH:ii:ss'.
	 */
	const ATT_TYPE_MYSQL_TIMESTAMP = 'mysql_timestamp';

	/**
	 * Mysql date and ISO 8601 formatted date field.
	 * This format is discouraged in Core+, but allowed.
	 *
	 * This stores the data as 'YYYY-mm-dd'.
	 */
	const ATT_TYPE_ISO_8601_DATE = 'ISO_8601_date';

	/**
	 * Validation for any non-blank value.
	 */
	const VALIDATION_NOTBLANK = "/^.+$/";

	/**
	 * Validation for a valid email address.
	 *
	 * Optionally can use DNS lookups to perform a more indepth validation of the domain.
	 */
	const VALIDATION_EMAIL = 'Core::CheckEmailValidity';
	// Regex to match email addresses.
	// @see http://www.regular-expressions.info/email.html
	//const VALIDATION_EMAIL = "/[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/";

	/**
	 * Validation for a valid protocol-based URL.
	 *
	 * This includes http, https, ftp, ssh, and any other protocol.
	 */
	const VALIDATION_URL = '#^[a-zA-Z]+://.+$#';

	/**
	 * Validation for a valid web URL.
	 *
	 * This will only match http:// or https://.
	 */
	const VALIDATION_URL_WEB = '#^[hH][tT][tT][pP][sS]{0,1}://.+$#';

	/**
	 * Definition for a model that has exactly one child table as a dependency.
	 *
	 * !WARNING! This gets deleted automatically if the parent is deleted!
	 */

	const LINK_HASONE  = 'one';
	/**
	 * Definition for a model that has several records from a child table for dependencies.
	 *
	 *  !WARNING! These get deleted automatically if the parent is deleted!
	 */
	const LINK_HASMANY = 'many';

	/**
	 * Definition for a model that *BELONGS* to one table.
	 *
	 * This is important because changes do not propagate up to the parent, as deleting the child
	 * should have no effect on the parent!
	 */
	const LINK_BELONGSTOONE = 'belongs_one';

	/**
	 * Definition for a model that *BELONGS* to many tables.
	 *
	 * A good example of this would be a linking table, that contains a Many-to-Many relationship
	 * between two models.
	 */
	const LINK_BELONGSTOMANY = 'belongs_many';

	/**
	 * Which DataModelInterface should this model execute its operations with.
	 * 99.9% of the time, it's fine to leave this as null, which will use the
	 * system DMI.  If however you want to utilize a Model with Memcache,
	 * (say for session information), it can be useful.
	 *
	 * @var DMI_Backend
	 */
	public $interface = null;

	/**
	 * @var array Associative array of the data in this corresponding record.
	 */
	protected $_data = array();

	/**
	 * The data according to the database.
	 * Useful for something.... :/
	 * @var array
	 */
	protected $_datainit = array();

	/**
	 * Some models have encrypted fields.  This will store the decrypted data for the application to access.
	 * @var array
	 */
	protected $_datadecrypted = null;

	/**
	 * Allow data to get overloaded onto models.
	 * This is common with Controllers tacking on extra data for templates to better handle the model.
	 * This data is not saved and does not effect the dirty flags.
	 *
	 * @var array
	 */
	protected $_dataother = array();

	/**
	 * @var boolean Flag to signify if this record exists in the database.
	 */
	protected $_exists = false;

	protected $_linked = array();

	protected $_cacheable = true;

	/**
	 * A cache of the schema for this object.
	 * This is useful because the public Schema definition can have columns that are omitted.
	 *
	 * @var array
	 */
	protected $_schemacache = null;

	/**
	 * The schema as per defined in the extending model.
	 * @var array
	 */
	public static $Schema = array();

	public static $Indexes = array();

	public static $_ModelCache = array();


	/**
	 * Create a new instance of the requested model.
	 * @param null $key
	 */
	public function __construct($key = null) {

		// Update the _data array based on the schema.
		$s = self::GetSchema();
		foreach ($s as $k => $v) {
			$this->_data[$k] = (isset($v['default'])) ? $v['default'] : null;
		}

		// Check the index (primary), and the incoming data.  If it matches, load it up!
		$i = self::GetIndexes();

		if (isset($i['primary']) && func_num_args() == sizeof($i['primary'])) {
			foreach ($i['primary'] as $k => $v) {
				$this->_data[$v] = func_get_arg($k);
			}
		}

		if($key !== null){
			$this->load();
		}
	}

	public function load() {

		// If there is no associated table, do not load anything.
		if (!self::GetTableName()) {
			return;
		}

		// I need to check the pks first.
		// If they're not set I can't load anything from the database.
		$i = self::GetIndexes();
		$s = self::GetSchema();

		$keys = array();
		if (isset($i['primary']) && sizeof($i['primary'])) {
			foreach ($i['primary'] as $k) {
				$v = $this->get($k);

				// 2012.12.15 cp - I am changing this from return to continue to support enterprise data in non-enterprise mode.
				// specifically, the page model.  Pages can be -1 for global or a specific ID for that site.
				// in non-multisite mode, there are no other sites, so -1 and 0 are synonymous even though it's a primary key.
				if ($v === null) continue;

				// Remember the PK's for the query lookup later on.
				$keys[$k] = $v;
			}
		}

		if ($this->_cacheable) {
			$cachekey = $this->_getCacheKey();
			$cache    = Core::Cache()->get($cachekey);

			// do something if cache succeeds....
		}

		// If the enterprise/multimode is set and enabled and there's a site column here,
		// that should be enforced at a low level.
		if(
			isset($s['site']) &&
			$s['site']['type'] == Model::ATT_TYPE_SITE &&
			Core::IsComponentAvailable('enterprise') &&
			MultiSiteHelper::IsEnabled() &&
			$this->get('site') === null
		){
			$keys['site'] = MultiSiteHelper::GetCurrentSiteID();
		}


		$data = Dataset::Init()
			->select('*')
			->table(self::GetTableName())
			->where($keys)
			->execute($this->interface);

		if ($data->num_rows) {
			$this->_data     = $data->current();
			$this->_datainit = $data->current();

			$this->_exists = true;
		}
		else {
			$this->_exists = false;
		}

		return;
	}

	/**
	 * Save this Model into the datastore.
	 *
	 * Return true if saved successfully, false if no change required,
	 * and will throw a DMI_Exception if there was an error.
	 *
	 * @return boolean
	 * @throws DMI_Exception
	 */
	public function save() {

		// Only do the same operation if it's been changed.
		// This is actually a little more in depth than simply seeing if it's been modified, as I also
		// have to look into the linked classes and see if it exists
		$save = false;
		// new models always get saved.
		if(!$this->_exists){
			$save = true;
		}
		// Models that have changed content always get saved.
		elseif($this->changed()){
			$save = true;
		}
		else{
			foreach($this->_linked as $k => $l){
				if(isset($l['records'])){
					$save = true;
					break;
				}
			}
		}

		if(!$save){
			return false;
		}
		/*
		// Do the key validation first of all.
		if (get_class($this) == 'PageModel') {
			$s = self::GetSchema();
			foreach ($this->_data as $k => $v) {
				// Date created and updated have their own validations.
				if (
					$s[$k]['type'] == Model::ATT_TYPE_CREATED ||
					$s[$k]['type'] == Model::ATT_TYPE_UPDATED
				) {
					if (!(is_numeric($v) || !$v)) throw new DMI_Exception('Unable to save ' . self::GetTableName() . '.' . $k . ' has an invalid value.');
					continue;
				}
				// This key null?
				if ($v === null && !(isset($s[$k]['null']) && $s[$k]['null'])) {
					if (!isset($s[$k]['default'])) throw new DMI_Exception('Unable to save ' . self::GetTableName() . '.' . $k . ', null is not allowed and there is no default value set.');
				}
			}
		}
		*/
		if ($this->_exists) $this->_saveExisting();
		else $this->_saveNew();


		// Go through any linked tables and ensure that they're saved as well.
		foreach($this->_linked as $k => $l){
			// No need to save if it was never loaded.
			if(!(isset($l['records']))) continue;

			if(!(isset($l['records']) || $this->changed())) continue; // No need to save if it was never loaded.

			switch($l['link']){
				case Model::LINK_HASONE:
				case Model::LINK_HASMANY:
					$models = (is_array($l['records']))? $l['records'] : array($l['records']);

					foreach($models as $model){
						// Ensure all linked fields still match up.  Something may have been changed in the parent.
						$model->setFromArray($this->_getLinkWhereArray($k));
						$model->save();
					}
					break;
				// There is no default behaviour... other than to ignore it.
			}
		}

		$this->_exists     = true;
		$this->_datainit = $this->_data;

		// Indicate that something happened.
		return true;
	}

	//// A few array access functions \\\\

	/**
	 * Whether an offset exists
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset An offset to check for.
	 *
	 * @return boolean Returns true on success or false on failure.
	 */
	public function offsetExists($offset) {
		return (array_key_exists($offset, $this->_data));
	}

	/**
	 * Offset to retrieve
	 *
	 * Alias of Model::get()
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param mixed $offset The offset to retrieve.
	 *
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset) {
		return $this->get($offset);
	}

	/**
	 * Offset to set
	 *
	 * Alias of Model::set()
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 *
	 * @return void
	 */
	public function offsetSet($offset, $value) {
		$this->set($offset, $value);
	}

	/**
	 * Offset to unset
	 *
	 * This just sets the value to null.
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset The offset to unset.
	 *
	 * @return void
	 */
	public function offsetUnset($offset) {
		$this->set($offset, null);
	}

	/**
	 * Get a valid schema of all keys of this model.
	 *
	 * @return array
	 */
	public function getKeySchemas() {
		if ($this->_schemacache === null) {
			$this->_schemacache = self::GetSchema();

			foreach ($this->_schemacache as $k => $v) {
				// These are all defaults for schemas.
				// Setting them to the default if they're not set will ensure that 
				// 'undefined index' notices are not incurred.
				if (!isset($v['type']))      $this->_schemacache[$k]['type']      = Model::ATT_TYPE_TEXT; // Default if not present.
				if (!isset($v['maxlength'])) $this->_schemacache[$k]['maxlength'] = false;
				if (!isset($v['null']))      $this->_schemacache[$k]['null']      = false;
				if (!isset($v['comment']))   $this->_schemacache[$k]['comment']   = false;
				if (!isset($v['default']))   $this->_schemacache[$k]['default']   = false;
				if (!isset($v['encrypted'])) $this->_schemacache[$k]['encrypted'] = false;
			}
		}

		return $this->_schemacache;
	}

	/**
	 * Get a valid schema of the requested key of this model.
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function getKeySchema($key) {
		$s = $this->getKeySchemas();

		if (!isset($s[$key])) return null;

		return $s[$key];
	}

	/*
	public boolean offsetExists ( mixed $offset )
	public mixed offsetGet ( mixed $offset )
	public void offsetSet ( mixed $offset , mixed $value )
	public void offsetUnset ( mixed $offset )
	*/
	private function _saveNew() {
		$i = self::GetIndexes();
		$s = self::GetSchema();
		$n = $this->_getTableName();

		if (!isset($i['primary'])) $i['primary'] = array(); // No primary schema defined... just don't make the in_array bail out.

		$dat = new Dataset();
		$dat->table($n);

		$idcol = false;
		foreach ($this->_data as $k => $v) {
			$keyschema = $s[$k];

			switch ($keyschema['type']) {
				case Model::ATT_TYPE_CREATED:
				case Model::ATT_TYPE_UPDATED:
					$nv = Time::GetCurrentGMT();
					$dat->insert($k, $nv);
					$this->_data[$k] = $nv;
					break;

				case Model::ATT_TYPE_ID:
					$dat->setID($k, $this->_data[$k]);
					$idcol = $k; // Remember this for after the save.
					break;

				case Model::ATT_TYPE_UUID:
					if($this->_data[$k]){
						// Yay, a UUID is already set, no need to really do much.
						$nv = $this->_data[$k];
						// It's already set and this will most likely be ignored, but may not be for UPDATE statements...
						// although there shouldn't be any update statements here.... but ya never know
						$dat->setID($k, $nv);
					}
					else{
						// I need to generate a new key and set that.
						$nv = Core::GenerateUUID();
						// In this case, the database isn't going to care what the column is, other than the fact it's unique.
						// It will be :)
						$dat->insert($k, $nv);
						// And I need to set this on the data so it's available next time.
						$this->_data[$k] = $nv;
						$dat->setID($k, $nv);
					}
					// Remember this for after the save.
					$idcol = $k;
					break;

				case Model::ATT_TYPE_SITE:
					if(
						Core::IsComponentAvailable('enterprise') &&
						MultiSiteHelper::IsEnabled() &&
						$v === null
					){
						$site = MultiSiteHelper::GetCurrentSiteID();
						$dat->insert('site', $site);
						$this->_data[$k] = $site;
					}
					else{
						$dat->insert($k, $v);
					}
					break;

				default:
					$dat->insert($k, $v);
					break;
			}
		}

		$dat->execute($this->interface);

		if ($idcol) $this->_data[$idcol] = $dat->getID();
	}

	/**
	 * Save an existing Model object into the database.
	 * Will create, set and execute a dataset object as appropriately internally.
	 *
	 * @param boolean @useset Set to true to have this model use an INSERT_UPDATE statement instead of just UPDATE.
	 * @return bool
	 */
	protected function _saveExisting($useset = false) {

		// If this model doesn't have any changes, don't save it!
		if(!$this->changed()) return false;

		$i = self::GetIndexes();
		$s = self::GetSchema();
		$n = $this->_getTableName();

		// No primary schema defined... just don't make the in_array bail out.
		if (!isset($i['primary'])) $i['primary'] = array();

		// This is the dataset object that will be integral in this function.
		$dat = new Dataset();
		$dat->table($n);

		$idcol = false;
		foreach ($this->_data as $k => $v) {
			if(!isset($s[$k])){
				// This key was not in the schema.  Probable reasons for this would be a column that was
				// removed from the schema in an upgrade, but was never removed from the database.
				// This is typical because the installer tries to be non-destructive when it comes to data.
				continue;
			}
			$keyschema = $s[$k];
			// Certain key types have certain functions.
			switch ($keyschema['type']) {
				case Model::ATT_TYPE_CREATED:
					// Already created... don't update the flag.
					continue 2;
				case Model::ATT_TYPE_UPDATED:
					// Update the updated timestamp with now.
					$nv = Time::GetCurrentGMT();
					$dat->update($k, $nv);
					$this->_data[$k] = $nv;
					continue 2;
				case Model::ATT_TYPE_ID:
				case Model::ATT_TYPE_UUID:
					$dat->setID($k, $this->_data[$k]);
					$idcol = $k; // Remember this for after the save.
					continue 2;
			}
			//var_dump($k, $i['primary']);
			// Everything else
			if (in_array($k, $i['primary'])) {
				// Just in case the new data changed....
				if ($this->_datainit[$k] != $v){
					if($useset){
						$dat->set($k, $v);
					}
					else{
						$dat->update($k, $v);
					}
				}

				$dat->where($k, $this->_datainit[$k]);

				$this->_data[$k] = $v;
			}
			else {
				if (isset($this->_datainit[$k]) && $this->_datainit[$k] == $v) continue; // Skip non-changed columns
				//echo "Setting [$k] = [$v]<br/>"; // DEBUG
				if($useset){
					$dat->set($k, $v);
				}
				else{
					$dat->update($k, $v);
				}
			}
		}

		// No data.. nothing to change I guess!
		// This is a failsafe should (for some reason), the changed() method doesn't return the correct value.
		if(!sizeof($dat->_sets)){
			return false;
		}
		//var_dump($dat); die('mep'); // DEBUG
		$dat->execute($this->interface);
		// IDs don't change in updates, else they wouldn't be the id.
	}

	/**
	 * Load this model from an associative array, or record.
	 * This is meant to be called from the Factory system, and the data passed in
	 * MUST be sanitized and valid!
	 *
	 * @param array $record
	 */
	public function _loadFromRecord($record) {

		$this->_data = $record;

		// And since this is supposed to be the initial load method... toggle the appropriate flags.
		$this->_datainit = $this->_data;
		$this->_exists   = true;
	}

	public function delete() {
		if ($this->exists()) {
			$n = $this->_getTableName();
			$i = self::GetIndexes();
			// Delete the data from the database.
			$dat = new Dataset();

			$dat->table($n);

			if (!isset($i['primary'])) {
				throw new Exception('Unable to delete model [ ' . get_class($this) . ' ] without any primary keys.');
			}

			foreach ($i['primary'] as $k) {
				$dat->where(array($k => $this->_data[$k]));
			}

			$dat->limit(1)->delete();

			if ($dat->execute($this->interface)) {
				$this->_exists = false;
			}
		}

		// Blank out any dependent records based on links.
		foreach ($this->_linked as $k => $l) {

			switch($l['link']){
				case Model::LINK_HASONE:
				case Model::LINK_HASMANY:
					// I can do this without actually calling them.
					$c     = $this->_getLinkClassName($k);
					$model = new $c();

					Dataset::Init()
						->table($model->getTableName())
						->where($this->_getLinkWhereArray($k))
						->delete()
						->execute($this->interface);
					break;
				// There is no default behaviour... other than to ignore it.
			}

			if (isset($this->_linked[$k]['records'])) unset($this->_linked[$k]['records']);
		}
	}

	/**
	 * Function to handle data validation for keys.
	 *
	 * This will lookup if any "validation" is set on the schema, and check it if it exists.
	 * This will not actually do any setting, simply return true or throw an exception, (if requested).
	 *
	 * @param string $k The key to validate
	 * @param mixed  $v The value to validate with
	 * @param bool   $throwexception Set to true if you would like this function to throw errors.
	 *
	 * @return bool|mixed|string
	 * @throws ModelValidationException
	 */
	public function validate($k, $v, $throwexception = false) {
		// Is there validation available for this key?
		$s = self::GetSchema();

		// Default is true, since by default there is no validation.
		$valid = true;

		if (isset($s[$k]['validation'])) {
			// Validation exists... check it.
			$check = $s[$k]['validation'];

			// Special "this" method.
			if (is_array($check) && sizeof($check) == 2 && $check[0] == 'this') {
				$valid = call_user_func(array($this, $check[1]), $v);
			}
			// Method-based validation.
			elseif (strpos($check, '::') !== false) {
				// the method can either be true, false or a string.
				// Only if true is returned will that be triggered as success.
				$valid = call_user_func($check, $v);
			}
			// regex-based validation.  These don't have any return strings so they're easier.
			elseif (
				($check{0} == '/' && !preg_match($check, $v)) ||
				($check{0} == '#' && !preg_match($check, $v))
			) {
				$valid = false;
			}
		}


		if ($valid === true) {
			// Validation's good, return true!
			return true;
		}


		// Failed?  Get a good message for the user.
		if ($valid === false) $msg = isset($s[$k]['validationmessage']) ? $s[$k]['validationmessage'] : $k . ' fails validation';
		else $msg = $valid;


		if ($throwexception) {
			// Validation failed and an Exception was requested.
			throw new ModelValidationException($msg);
		}
		else {
			// Validation failed, but just return the message.
			return $msg;
		}
	}

	/**
	 * Translate a key to the strict version of it.
	 * ie: if a given key is a "Boolean" and the string "true" is given, that should be resolved to 1.
	 *
	 * @param string $k The key to lookup
	 * @param mixed $v  The value to check
	 *
	 * @return mixed The translated value
	 */
	public function translateKey($k, $v){
		$s = self::GetSchema();

		// Not in the schema.... just return the value unmodified.
		if(!isset($s[$k])) return $v;

		$type = $s[$k]['type']; // Type is one of the required properties.

		if ($type == Model::ATT_TYPE_BOOL) {
			switch(strtolower($v)){
				// This is used by checkboxes
				case 'yes':
					// A single checkbox will have the value of "on" if checked
				case 'on':
					// Hidden inputs will have the value of "1"
				case 1:
					// sometimes the string "true" is sent.
				case 'true':
					$v = 1;
					break;
				default:
					$v = 0;
			}
		}

		return $v;
	}

	/**
	 * Set a value of a specific key.
	 *
	 * The data is validated automatically as per the specific Model specifications.
	 *
	 * This supports data overloading.
	 *
	 * @param string $k The key to set
	 * @param mixed  $v The value to set
	 *
	 * @return boolean True on success, false on no change needed
	 * @throws ModelValidationException
	 */
	public function set($k, $v) {
		// $this->_data will always have the schema keys at least set to null.
		if (array_key_exists($k, $this->_data)) {

			if($this->_data[$k] === null && $v === null) return false; // No change needed.
			elseif ($this->_data[$k] !== null && $this->_data[$k] == $v) return false; // No change needed.

			// Is there validation for this key?
			// That function will handle all of this logic, (including the exception throwing)
			$this->validate($k, $v, true);

			// Some model types get special treatment with the translation function... ie: booleans.
			// everything else will simply return the unmodified value.
			$v = $this->translateKey($k, $v);

			// Set the propagation FIRST, that way I have the old key in memory to lookup.
			$this->_setLinkKeyPropagation($k, $v);

			// See if this is an encrypted field first.  If it is... set the decrypted version and encrypt it.
			$keydat = $this->getKeySchema($k);
			if($keydat['encrypted']){
				$this->decryptData();
				$this->_datadecrypted[$k] = $v;
				$this->_data[$k] = $this->encryptValue($v);
			}
			else{
				$this->_data[$k] = $v;
			}
			return true;
		}
		else {
			// Ok, let data to get overloaded for convenience sake.
			// This doesn't get any validation or anything however.
			$this->_dataother[$k] = $v;
			return true;
		}
	}

	/*
	 * Go through any linked tables and update them if the linking key has been changed.
	 *
	 * This needs to actually go through the database and update the saved keys if necessary.
	 *
	 * @param type $key
	 */
	protected function _setLinkKeyPropagation($key, $newval) {
		// If this model does not exist yet, there will be no information linked in the database.
		$exists = $this->exists();

		foreach ($this->_linked as $lk => $l) {

			// I can't change my parent data.
			// NO CHANGING YOUR PARENTS!
			if($l['link'] == Model::LINK_BELONGSTOONE) continue;
			if($l['link'] == Model::LINK_BELONGSTOMANY) continue;

			$dolink = false;
			// I can't use the getLinkWhereArray function, because that will resolve the key.
			// I need the key itself.
			if (!isset($l['on'])) {
				// @todo automatic linking
			}
			elseif (is_array($l['on'])) {
				foreach ($l['on'] as $k => $v) {
					if (is_numeric($k) && $v == $key) $dolink = true;
					elseif (!is_numeric($k) && $k == $key) $dolink = true;
				}
			}
			else {
				if ($l['on'] == $key) $dolink = true;
			}

			// $dolink should now be true/false.  If true I need to load that linked table and set the new key.
			if (!$dolink) continue;

			if($exists){
				// Get the data and update it!
				$links = $this->getLink($lk);
				if (!is_array($links)) $links = array($links);

				foreach ($links as $model) {
					$model->set($key, $newval);
				}
			}
			else{
				// Only update the cached data, as nothing has been saved, so the database doesn't have anything.
				if(!isset($this->_linked[$lk]['records'])) continue;

				foreach($this->_linked[$lk]['records'] as $model){
					$model->set($key, $newval);
				}
			}
		}
	}

	protected function _getLinkClassName($linkname) {
		// Determine the class.
		$c = (isset($this->_linked[$linkname]['class'])) ? $this->_linked[$linkname]['class'] : $linkname . 'Model';

		if (!is_subclass_of($c, 'Model')) return null; // @todo Error Handling

		return $c;
	}


	/**
	 * Get the where array of criteria for a given link.
	 * Useful for manually tweaking the clause.
	 *
	 * @param $linkname
	 *
	 * @return array|null
	 */
	protected function _getLinkWhereArray($linkname) {
		if (!isset($this->_linked[$linkname])) return null; // @todo Error Handling

		// Build a standard where criteria that can be used throughout this function.
		$wheres = array();

		if (!isset($this->_linked[$linkname]['on'])) {
			return null; // @todo automatic linking.
		}
		elseif (is_array($this->_linked[$linkname]['on'])) {
			foreach ($this->_linked[$linkname]['on'] as $k => $v) {
				if (is_numeric($k)) $wheres[$v] = $this->get($v);
				else $wheres[$k] = $this->get($v);
			}
		}
		else {
			$k          = $this->_linked[$linkname]['on'];
			$wheres[$k] = $this->get($k);
		}


		// Pages have a special extra here.  If it's enterprise/multisite mode, enforce that relationship.
		if($linkname == 'Page' && Core::IsComponentAvailable('enterprise') && MultiSiteHelper::IsEnabled()){
			// See if there's a site column on this schema.  If there is, enforce that binding too!
			$schema = self::GetSchema();
			if(isset($schema['site']) && $schema['site']['type'] == Model::ATT_TYPE_SITE){
				$wheres['site'] = $this->get('site');
			}
		}


		return $wheres;
	}


	/**
	 * Get the model factory for a given link.
	 *
	 * Useful for manipulating the factory of the data.
	 *
	 * @param $linkname
	 *
	 * @return ModelFactory
	 */
	public function getLinkFactory($linkname){
		if (!isset($this->_linked[$linkname])) return null; // @todo Error Handling

		$c = $this->_getLinkClassName($linkname);

		$f = new ModelFactory($c);
		switch($this->_linked[$linkname]['link']){
			case Model::LINK_HASONE:
			case Model::LINK_BELONGSTOONE:
				$f->limit(1);
				break;
		}

		$wheres = $this->_getLinkWhereArray($linkname);
		$f->where($wheres);

		// Yup, no get or anything, just build the factory.
		return $f;
	}


	/**
	 * Get linked models to this model based on a link name
	 *
	 * If the link type is a one-to-one or many-to-one, (HASONE), a single Model is returned.
	 * else this behaves as the Find function, where an array of models is returned.
	 *
	 * @param      $linkname
	 * @param null $order
	 *
	 * @return Model|array
	 */
	public function getLink($linkname, $order = null) {
		if (!isset($this->_linked[$linkname])) return null; // @todo Error Handling

		// Try to keep these in cache, so when they change I'll be able to save them on the parent's save function.
		if (!isset($this->_linked[$linkname]['records'])) {

			$f = $this->getLinkFactory($linkname);
			$c = $this->_getLinkClassName($linkname);
			$wheres = $this->_getLinkWhereArray($linkname);

			if ($order) $f->order($order);
			$this->_linked[$linkname]['records'] = $f->get();

			// Ensure that it's a valid record and not null.  If it's a LINK_ONE, the factory will return null if it doesn't exist.
			if ($this->_linked[$linkname]['records'] === null) {
				$this->_linked[$linkname]['records'] = new $c();
				foreach ($wheres as $k => $v) {
					$this->_linked[$linkname]['records']->set($k, $v);
				}
			}
		}

		return $this->_linked[$linkname]['records'];
	}

	/*
	 * In 1-to-1 mode, this returns either the single record matched or nothing at all.
	 * In 1-to-M mode, this returns an attached object with the requested search keys, either bound or new.
	 *
	 * @param type $linkname
	 * @param type $searchkeys
	 */
	public function findLink($linkname, $searchkeys = array()) {
		$l = $this->getLink($linkname);
		if ($l === null) return null;

		// aka 1-to-1 mode.
		if (!is_array($l)) {
			$f = true;
			foreach ($searchkeys as $k => $v) {
				if ($l->get($k) != $v) {
					$f = false;
					break;
				}
			}
			return ($f) ? $l : false;
		}
		else {
			foreach ($l as $model) {
				$f = true;
				foreach ($searchkeys as $k => $v) {
					if ($model->get($k) != $v) {
						$f = false;
						break;
					}
				}
				if ($f) return $model;
			}

			// Still here?  Guess it didn't find it.
			// Create the element, attach it and return that!
			$c = $this->_getLinkClassName($linkname);
			// @var Model $model
			$model = new $c();
			$model->setFromArray($this->_getLinkWhereArray($linkname));
			$model->setFromArray($searchkeys);
			$model->load();
			$this->_linked[$linkname]['records'][] = $model;
			return $model;
		}
		// Search through each of these and find a matching 
	}

	/**
	 * Administrative method used internally by some systems.  This allows a link to be overwritten externally.
	 *
	 * Particularly useful for BELONGSTOONE models being updated by their parent.
	 *
	 * @param       $linkname
	 * @param Model $model
	 *
	 * @return void
	 */
	public function setLink($linkname, Model $model) {
		if (!isset($this->_linked[$linkname])) return; // @todo Error Handling

		// Update the cached model.
		switch($this->_linked[$linkname]['link']){
			case Model::LINK_HASONE:
			case Model::LINK_BELONGSTOONE:
				$this->_linked[$linkname]['records'] = $model;
				break;
			case Model::LINK_HASMANY:
			case Model::LINK_BELONGSTOMANY:
				if(!isset($this->_linked[$linkname]['records'])) $this->_linked[$linkname]['records'] = array();
				$this->_linked[$linkname]['records'][] = $model;
				break;
		}
	}

	/**
	 * Reset the linked models in this model.  Useful for deleting a child and not wanting them to come back as linked.
	 *
	 * @param $linkname
	 */
	public function resetLink($linkname){
		if (!isset($this->_linked[$linkname])) return; // @todo Error Handling

		$this->_linked[$linkname]['records'] = null;
	}

	/**
	 * Set properties on this model from an associative array of key/value pairs.
	 *
	 * @param $array
	 */
	public function setFromArray($array) {
		foreach ($array as $k => $v) {
			$this->set($k, $v);
		}
	}

	/**
	 * Set properties on this model from a form object, optionally with a specific prefix.
	 *
	 * @param Form        $form   Form object to pull data from
	 * @param string|null $prefix Prefix that all keys should be matched to, (optional)
	 */
	public function setFromForm(Form $form, $prefix = null){

		// Get every "prefix[...]" element, as they key up 1-to-1.
		$els = $form->getElements(true, false);
		foreach ($els as $e) {
			// If a specific prefix was requested and this element does not match up, skip it!
			if ($prefix){
				if(!preg_match('/^' . $prefix . '\[(.*?)\].*/', $e->get('name'), $matches)) continue;
				$key = $matches[1];
			}
			else{
				$key = $e->get('name');
			}

			$val    = $e->get('value');
			$schema = $this->getKeySchema($key);

			// If there is no schema entry for this key, no reason to try to set it as it doesn't exist in the Model.
			if(!$schema) continue;

			if ($schema['type'] == Model::ATT_TYPE_BOOL) {
				// This is used by checkboxes
				if (strtolower($val) == 'yes') $val = 1;
				// A single checkbox will have the value of "on" if checked
				elseif (strtolower($val) == 'on') $val = 1;
				// Hidden inputs will have the value of "1"
				elseif ($val == 1) $val = 1;
				else $val = 0;
			}

			$this->set($key, $val);
		}
	}

	/**
	 * Converse to setFromForm, this method is called on each form element created when calling addModel or BuildFromModel.
	 *
	 * Any special instructions for your model's elements can go here, simply extend this method and add logic as necessary.
	 *
	 * @param             $key
	 * @param FormElement $element
	 */
	public function setToFormElement($key, FormElement $element){
		// This method left intentionally blank.
		// If custom logic is required here, extend this method in your model and do so there.
	}

	/**
	 * Method that is called on the model after "addModel" is called on a form.
	 *
	 * Any special logic such as adding custom elements from the model can be done here, simply extend this method and add logic as necessary.
	 *
	 * @param Form   $form
	 * @param string $prefix
	 */
	public function addToFormPost(Form $form, $prefix){
		// This method left intentionally blank.
		// If custom logic is required here, extend this method in your model and do so there.
	}


	/**
	 * Get the requested key for this object.
	 *
	 * @param string $k
	 *
	 * @return mixed
	 */
	public function get($k) {
		if($this->_datadecrypted !== null && array_key_exists($k, $this->_datadecrypted)){
			return $this->_datadecrypted[$k];
		}
		elseif (array_key_exists($k, $this->_data)) {
			return $this->_data[$k];
		}
		elseif (array_key_exists($k, $this->_dataother)) {
			return $this->_dataother[$k];
		}
		else {
			return null;
		}
	}

	/**
	 * Just return this object as an array
	 * (essentially just the _data array... :p)
	 *
	 * @return array
	 */
	public function getAsArray() {
		// Has there been data that has been decrypted?
		if($this->_datadecrypted !== null){
			return array_merge($this->_data, $this->_dataother, $this->_datadecrypted);
		}
		else{
			return array_merge($this->_data, $this->_dataother);
		}
	}

	/**
	 * Get if this model exists in the datastore already.
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->_exists;
	}

	/**
	 * Get if this model is a new entity that doesn't exist in the datastore.
	 *
	 * @return bool
	 */
	public function isnew() {
		return !$this->_exists;
	}

	/**
	 * Get if this model has changes that are pending to be applied back to the datastore.
	 *
	 * @return bool
	 */
	public function changed(){
		$s = self::GetSchema();

		foreach ($this->_data as $k => $v) {
			if(!isset($s[$k])){
				// This key was not in the schema.  Probable reasons for this would be a column that was
				// removed from the schema in an upgrade, but was never removed from the database.
				// This is typical because the installer tries to be non-destructive when it comes to data.
				continue;
			}
			$keyschema = $s[$k];

			// Certain key types are to be ignored in the "changed" logic check.  Namely automatic timestamps.
			switch ($keyschema['type']) {
				case Model::ATT_TYPE_CREATED:
				case Model::ATT_TYPE_UPDATED:
					continue 2;
			}

			// It's a standard column, check and see if it matches the datainit value.
			// If the datainit key doesn't exist, that also constitutes as a changed flag!
			if(!isset($this->_datainit[$k])){
				//echo "$k changed!"; // DEBUG
				return true;
			}

			if($this->_datainit[$k] != $this->_data[$k]){
				//echo "$k changed!"; // DEBUG
				return true;
			}

			// The data seems to have matched up, nothing to see here, move on!
		}

		// Oh, if it's gotten past all the data keys, then the data must have been identical!
		return false;
	}

	/**
	 * Function to call to decrypt data from this model.
	 *
	 * NOTE, to increase performance, data is NOT automatically decrypted upon loading data from the datastore!
	 */
	public function decryptData(){
		if($this->_datadecrypted === null){
			$this->_datadecrypted = array();

			foreach($this->getKeySchemas() as $k => $v){
				// Since certain keys in a model may be encrypted.
				if($v['encrypted']){
					$payload = $this->_data[$k];
					if($payload === null || $payload === ''){
						$this->_datadecrypted[$k] = null;
						continue;
					}

					preg_match('/^\$([^$]*)\$([0-9]*)\$(.*)$/m', $payload, $matches);

					$cipher = $matches[1];
					$passes = $matches[2];
					$size = openssl_cipher_iv_length($cipher);
					// Now I can trim off the beginning crap from the encrypted string.
					$dec = substr($payload, strlen($cipher) + 5, 0-$size);
					$iv = substr($payload, 0-$size);

					for($i=0; $i<$passes; $i++){
						$dec = openssl_decrypt($dec, $cipher, SECRET_ENCRYPTION_PASSPHRASE, true, $iv);
					}

					$this->_datadecrypted[$k] = $dec;
				}
			}
		}
	}

	/**
	 * Get the table name for this class
	 *
	 * @return null|string
	 */
	public function _getTableName(){
		return self::GetTableName();
	}

	/**
	 * Method to encrypt a specific key for storage.
	 *
	 * Called internally by the set function.
	 * Will return the encrypted data.
	 *
	 * @param $key string
	 * @return string Encrypted data
	 */
	protected function encryptValue($value){
		$cipher = 'AES-256-CBC';
		$passes = 10;
		$size = openssl_cipher_iv_length($cipher);
		$iv = mcrypt_create_iv($size, MCRYPT_RAND);

		$enc = $value;
		for($i=0; $i<$passes; $i++){
			$enc = openssl_encrypt($enc, $cipher, SECRET_ENCRYPTION_PASSPHRASE, true, $iv);
		}

		$payload = '$' . $cipher . '$' . str_pad($passes, 2, '0', STR_PAD_LEFT) . '$' . $enc . $iv;
		return $payload;
	}


	protected function _getCacheKey() {
		if (!$this->_cacheable) return false;
		if (!(isset($i['primary']) && sizeof($i['primary']))) return false;

		$cachekeys = array();
		foreach ($i['primary'] as $k) {
			$val = $this->get($k);
			if ($val === null) $val = 'null';
			elseif ($val === false) $val = 'false';
			$cachekeys[] = $val;
		}

		return 'DATA:' . self::GetTableName() . ':' . implode('-', $cachekeys);
	}


	///////////////////    Factory-Related Static Methods

	/**
	 * Constructor alternative that utilizes caching to save on database lookups.
	 *
	 * @static
	 *
	 * @param $keys
	 * @return Model
	 */
	public static function Construct($keys = null){
		$class = get_called_class();

		// New ones do not get cached.
		if($keys === null){
			return new $class();
		}

		$cache = '';
		foreach(func_get_args() as $a){
			$cache .= $a . '-';
		}
		$cache = substr($cache, 0, -1);

		if(!isset(self::$_ModelCache[$class])){
			self::$_ModelCache[$class] = array();
		}
		if(!isset(self::$_ModelCache[$class][$cache])){
			$reflection = new ReflectionClass($class);
			/** @var $obj Model */
			$obj = $reflection->newInstanceArgs(func_get_args());

			self::$_ModelCache[$class][$cache] = $obj;
		}

		//var_dump($cache);
		return self::$_ModelCache[$class][$cache];
	}

	/**
	 * Factory shortcut function to do a search for the specific records.
	 *
	 * @static
	 * @param array|string    $where Where clause
	 * @param int|string|null $limit Limit clause
	 * @param string|null     $order Order clause
	 *
	 * @return array|null|Model
	 */
	public static function Find($where = array(), $limit = null, $order = null) {
		$fac = new ModelFactory(get_called_class());
		$fac->where($where);
		$fac->limit($limit);
		$fac->order($order);
		//var_dump($fac);
		return $fac->get();
	}

	/**
	 * Factory shortcut function to do a search for the specific records and return them as a raw array.
	 *
	 * @static
	 *
	 * @param array $where
	 * @param null  $limit
	 * @param null  $order
	 *
	 * @return array
	 */
	public static function FindRaw($where = array(), $limit = null, $order = null) {
		$fac = new ModelFactory(get_called_class());
		$fac->where($where);
		$fac->limit($limit);
		$fac->order($order);
		//var_dump($fac);
		return $fac->getRaw();
	}

	/**
	 * Get a count of records that match a given where criteria
	 *
	 * @static
	 *
	 * @param array $where
	 *
	 * @return int
	 */
	public static function Count($where = array()) {
		$fac = new ModelFactory(get_called_class());
		$fac->where($where);
		return $fac->count();
	}


	/*******************   Other Static Methods *************************/

	/**
	 * Get the table name for a given Model object
	 *
	 * @static
	 * @return string
	 */
	public static function GetTableName() {
		// Just a lookup table for rendered table names.
		// This is useful so the regex functions don't have to run more than once.
		static $_tablenames = array();
		$m = get_called_class();

		// Generic models cannot have tables.
		if ($m == 'Model') return null;

		if (!isset($_tablenames[$m])) {
			// Calculate the table class.

			// It's based on the main class's name.
			$tbl = $m;
			//$tbl = get_class($this);

			// If it ends in Model... trim that bit off.  It's assumed that the prefix is what we want.
			if (preg_match('/Model$/', $tbl)) $tbl = substr($tbl, 0, -5);

			// Replace any capitalized letters with a _[letter].
			$tbl = preg_replace('/([A-Z])/', '_$1', $tbl);

			// Of course this would produce something similar to _Foo_Mep_Blah.. don't need the beginning _.
			if ($tbl{0} == '_') $tbl = substr($tbl, 1);

			// And lowercase.
			$tbl = strtolower($tbl);

			// Prepend the DB_PREFIX and save!
			$_tablenames[$m] = DB_PREFIX . $tbl;
		}

		return $_tablenames[$m];
	}

	public static function GetSchema() {
		// Because the "Model" class doesn't have a schema... that's up to classes that extend it.
		$m = get_called_class();

		return $m::$Schema;
	}

	public static function GetIndexes() {
		//// Because the "Model" class doesn't have a schema... that's up to classes that extend it.
		$m = get_called_class();

		return $m::$Indexes;
	}
}

/**
 * Factory utility for models.
 *
 * This class provides an interface for searching for and counting models.  Generally, there are shortcut functions
 * available on the Model class that utilize this class, namely Count, Find, and FindRaw.
 */
class ModelFactory {

	/**
	 * Which DataModelInterface should this model execute its operations with.
	 * 99.9% of the time, it's fine to leave this as null, which will use the
	 * system DMI.  If however you want to utilize a Model with Memcache,
	 * (say for session information), it can be useful.
	 *
	 * @var DMI_Backend
	 */
	public $interface = null;

	/**
	 * What model is this a factory of?
	 * @var string
	 */
	private $_model;

	/**
	 * Contains the dataset object for this search.
	 *
	 * @var Dataset
	 */
	private $_dataset;


	public function __construct($model) {

		$this->_model = $model;

		$m              = $this->_model;
		$this->_dataset = new Dataset();
		$this->_dataset->table($m::GetTablename());
		$this->_dataset->select('*');
	}

	/**
	 * Where clause for the search, passed directly to the dataset object.
	 */
	public function where() {
		call_user_func_array(array($this->_dataset, 'where'), func_get_args());
	}

	public function whereGroup() {
		call_user_func_array(array($this->_dataset, 'whereGroup'), func_get_args());
	}

	public function order() {
		call_user_func_array(array($this->_dataset, 'order'), func_get_args());
	}

	public function limit() {
		call_user_func_array(array($this->_dataset, 'limit'), func_get_args());
	}

	/**
	 * Get the result or results from this factory.
	 *
	 * If limit is set to 1, either a Model or null is returned.
	 * Else, an array of Models is returned, be it populated or empty.
	 *
	 * @return array|null|Model
	 */
	public function get() {
		$this->_performMultisiteCheck();
		$rs = $this->_dataset->execute($this->interface);

		$ret = array();
		foreach ($rs as $row) {
			$model = new $this->_model();
			$model->_loadFromRecord($row);
			$ret[] = $model;
		}


		// Only return the model if "1" was requested as the limit.
		if ($this->_dataset->_limit == 1) {
			return (sizeof($ret)) ? $ret[0] : null;
		}
		else {
			return $ret;
		}
	}

	/**
	 * Get the results from this factory as a raw associative array.
	 *
	 * @return array
	 */
	public function getRaw(){
		$rs = $this->_dataset->execute($this->interface);

		return $rs->_data;
	}

	/**
	 * Get a count of how many records are in this factory
	 * (without counting the records one by one)
	 *
	 * @return int
	 */
	public function count() {
		$clone = clone $this->_dataset;
		$rs    = $clone->count()->execute($this->interface);

		return $rs->num_rows;
	}

	/**
	 * Get the raw dataset object for this factory.
	 * This can sometimes be useful for advanced manipulations of the low-level object.
	 *
	 * @since 2.1
	 * @return Dataset
	 */
	public function getDataset(){
		return $this->_dataset;
	}

	/**
	 * Internal function to do the multisite check on the model.
	 * If the model supports a site attribute and none requested, then set it to the current site.
	 */
	private function _performMultisiteCheck(){

		$m = $this->_model;
		$schema = $m::GetSchema();

		// Is there a site property?  If not I don't even care.
		if(
			isset($schema['site']) &&
			$schema['site']['type'] == Model::ATT_TYPE_SITE &&
			Core::IsComponentAvailable('enterprise') &&
			MultiSiteHelper::IsEnabled()
		){
			// I want to look it up because if the script actually set the site, then
			// it evidently wants it for a reason.
			$matches = $this->_dataset->getWhereClause()->findByField('site');
			if(!sizeof($matches)){
				$this->_dataset->where('site = ' . MultiSiteHelper::GetCurrentSiteID());
			}
		}
	}


	/**
	 * @since 2.4.0
	 * @param $model
	 *
	 * @return ModelSchema
	 */
	public static function GetSchema($model){
		$s = new ModelSchema($model);
		return $s;
	}
}


class ModelException extends Exception {
}

class ModelValidationException extends ModelException {
}

class ModelSchema {
	/**
	 * An associative array of ModelSchemaColumn objects.
	 *
	 * @var array
	 */
	public $definitions = array();

	/**
	 * An indexed array of the names of the columns in this schema.
	 *
	 * @var array
	 */
	public $order = array();

	public $indexes = array();

	public function __construct($model = null){
		if($model !== null){
			$this->readModel($model);
		}
	}

	public function readModel($model){
		$vars = get_class_vars($model);
		$schema = $vars['Schema'];

		foreach($schema as $name => $def){
			$column = new ModelSchemaColumn();
			$column->field = $name;
			foreach($def as $k => $v){
				$column->{$k} = $v;
			}

			// Some defaults.
			if($column->type == Model::ATT_TYPE_STRING && !$column->maxlength){
				$column->maxlength = 255;
			}

			if($column->type == Model::ATT_TYPE_ID && !$column->maxlength){
				$column->maxlength = 15;
				$column->autoinc = true;
			}

			if($column->type == Model::ATT_TYPE_UUID){
				// A UUID is in the format of:
				// siteid-timestamp-randomhex
				// or [1-3 numbers] - [11-12 hex] - [4 hex]
				// a total of up to 21 digits.
				$column->maxlength = 21;
				$column->autoinc = false;
			}

			if($column->type == Model::ATT_TYPE_INT && !$column->maxlength){
				$column->maxlength = 15;
			}

			if($column->type == Model::ATT_TYPE_CREATED && !$column->maxlength){
				$column->maxlength = 15;
			}

			if($column->type == Model::ATT_TYPE_UPDATED && !$column->maxlength){
				$column->maxlength = 15;
			}

			if($column->type == Model::ATT_TYPE_SITE){
				$column->default = 0;
				$column->comment = 'The site id in multisite mode, (or 0 otherwise)';
				$column->maxlength = 15;
			}

			// Is default not set?  Some columns would really like this to be!
			if($column->default === false && !$column->null){
				switch($column->type){
					case Model::ATT_TYPE_INT:
					case Model::ATT_TYPE_BOOL:
					case Model::ATT_TYPE_CREATED:
					case Model::ATT_TYPE_UPDATED:
					case Model::ATT_TYPE_FLOAT:
						$column->default = 0;
						break;
					case Model::ATT_TYPE_ISO_8601_DATE:
						$column->default = '0000-00-00';
						break;
					case Model::ATT_TYPE_ISO_8601_DATETIME:
						$column->default = '0000-00-00 00:00:00';
						break;
					default:
						$column->default = '';
				}
			}

			$this->definitions[$name] = $column;
			$this->order[] = $name;
		}

		$this->indexes = $vars['Indexes'];
		foreach($this->indexes as $key => $dat){
			if(!is_array($dat)){
				// Models can be defined with a single element for its index.
				// This is an acceptable shorthand standard, but the lower level utilities
				// are expecting an array.
				$this->indexes[$key] = array($dat);
			}
		}
	}

	/**
	 * Get a column by order (int) or name
	 *
	 * @param string|int $column
	 * @return ModelSchemaColumn|null
	 */
	public function getColumn($column){
		// This will resolve an int to the column name.
		if(is_int($column)){
			if(isset($this->order[$column])) $column = $this->order[$column];
			else return null;
		}

		if(isset($this->definitions[$column])) return $this->definitions[$column];
		else return null;
	}

	/**
	 * Get an array of differences between this schema and another schema.
	 *
	 * @param ModelSchema $schema
	 * @return array
	 */
	public function getDiff(ModelSchema $schema){
		$diffs = array();

		// This will only check for incoming changes.  If schema B (that schema), has a column that
		// schema A does not, flag that as different.
		// If schema A has a column that schema B does not, ignore that change as it is not relevant.
		// Do the same for the order; ignore columns that A has but B does not.
		foreach($schema->definitions as $name => $dat){
			$thiscol = $this->getColumn($name);

			// This model doesn't have a column the other one has... DIFFERENCE!
			if(!$thiscol){
				$diffs[] = array(
					'title' => 'A does not have column ' . $name,
					'type' => 'column',
				);
				continue;
			}

			if(!$thiscol->isDataIdentical($dat)){
				$diffs[] = array(
					'title' => 'Column ' . $name . ' does not match up',
					'type' => 'column',
				);
			}
		}

		$a_order = $this->order;
		foreach($this->definitions as $name => $dat){
			// If A has a column but B does not, drop that from the b order so the checks are accurate.
			if(!$schema->getColumn($name)) unset($a_order[array_search($name, $a_order)]);
		}

		// Check the order of them.
		if(implode(',', $a_order) != implode(',', $schema->order)){
			$diffs[] = array(
				'title' => 'Order of columns is different',
				'type' => 'order',
			);
		}

		// And lastly, the indexes.
		$thisidx = '';
		foreach($this->indexes as $name => $cols) $thisidx .= ';' . $name . '-' . implode(',', $cols);
		$thatidx = '';
		foreach($this->indexes as $name => $cols) $thatidx .= ';' . $name . '-' . implode(',', $cols);

		if($thisidx != $thatidx){
			$diffs[] = array(
				'title' => 'Indexes do not match up',
				'type' => 'index'
			);
		}

		return $diffs;
	}

	/**
	 * Test if this schema is identical (from a datastore perspective) to another model schema.
	 *
	 * Useful for reinstallations.
	 *
	 * @param ModelSchema $schema
	 * @return bool
	 */
	public function isDataIdentical(ModelSchema $schema){
		// Get a diff of the two.
		$diff = $this->getDiff($schema);

		// And see if there is something there.
		return !sizeof($diff);
	}
}

class ModelSchemaColumn {
	/**
	 * The field name or key name of this column
	 * @var string
	 */
	public $field;
	/**
	 * Specifies the data type contained in this column.  Must be one of the Model::ATT_TYPE_* fields.
	 * @var string
	 */
	public $type = Model::ATT_TYPE_TEXT;
	/**
	 * Set to true to disallow blank values
	 * @var bool
	 */
	public $required = false;
	/**
	 * Maximum length in characters (or bytes), of data stored.
	 * @var bool|int
	 */
	public $maxlength = false;
	/**
	 * Validation options for this column.
	 *
	 * The validation logic for data in the column, can be a regex (indicated by "/ ... /" or "# ... #"),
	 * a public static method ("SomeClass::ValidateSomething"), or an internal method ("this::validateField").
	 *
	 * @var null|string
	 */
	public $validation = null;
	/**
	 * ATT_TYPE_ENUM column types expect a set of values.  This is defined here as an array.
	 * @var null|array
	 */
	public $options = null;
	/**
	 * Default value to use for this column
	 * @var bool|int|float|string
	 */
	public $default = false;
	/**
	 * Allow null values for this column.  If set to true, null is preserved as null.  False will change null values to blank.
	 * @var bool
	 */
	public $null = false;
	/**
	 * Shortcut for specifying the form type when rendering as a form.  Should be a valid form type
	 * @var string
	 */
	public $formtype = 'text';
	/**
	 * Full version of specifying form parameters for this column.
	 * @var array
	 */
	public $form = array();
	/**
	 * Comment to add onto the database column.  Useful for administrative comments for.
	 * @var string
	 */
	public $comment = '';
	/**
	 * ATT_TYPE_FLOAT supports precision for its data.  Should be set as a string such as "6,2" for 6 digits left of decimal,
	 * 2 digits right of decimal.
	 * @var null|string
	 */
	public $precision = null;
	/**
	 * Core+ allows data to be encrypted / decrypted on-the-fly.  This is useful for sensitive information such as
	 * credit card data or authorization credentials for external sources.  Setting this to true will store all
	 * information as encrypted, and allow it to be read decrypted.
	 * @var bool
	 */
	public $encrypted = false;

	/**
	 * Indicator if this column needs to be auto incremented from the datamodel.
	 * @var bool
	 */
	public $autoinc = false;

	/**
	 * Check to see if this column is datastore identical to another column.
	 *
	 * @param ModelSchemaColumn $col
	 * @return bool
	 */
	public function isDataIdentical(ModelSchemaColumn $col){
		// The columns that matter....
		if($this->field != $col->field)       return false;

		//if($this->required != $col->required) return false;
		if($this->maxlength != $col->maxlength) return false;
		if($this->null != $col->null) return false;
		if($this->comment != $col->comment) return false;
		if($this->precision != $col->precision) return false;
		if($this->autoinc !== $col->autoinc) return false;

		// Default is a bit touchy because it can have database-specific defaults if not set locally.
		if($this->default === false){
			// I don't care what the database is, it'll pick its own defaults.
		}
		elseif($this->default === $col->default){
			// They're identical... yay!
		}
		elseif(Core::CompareValues($this->default, $col->default)){
			// They're close enough....
			// Core will check and see if val1 === (string)"12" and val2 === (int)12.
			// Consider it a fuzzy comparison that actually acknowledges the difference between NULL, "", and 0.
		}
		elseif($col->default === false && $this->default !== false){
			return false;
		}
		else{
			return false;
		}

		// If one is an array but not the other....
		if(is_array($this->options) != is_array($col->options)) return false;

		if(is_array($this->options) && is_array($col->options)){
			// If they're both arrays, I need another way to check them.
			if(implode(',', $this->options) != implode(',', $col->options)) return false;
		}

		// Type needs to allow for a few special cases.
		// Here, there are several columns that are all identical.
		$typematches = array(
			array(
				Model::ATT_TYPE_INT,
				Model::ATT_TYPE_UUID,
				Model::ATT_TYPE_CREATED,
				Model::ATT_TYPE_UPDATED,
				Model::ATT_TYPE_SITE,
			)
		);

		$typesidentical = false;
		foreach($typematches as $types){
			if(in_array($this->type, $types) && in_array($col->type, $types)){
				// Found an identical pair!  break out to continue;
				$typesidentical = true;
				break;
			}
		}

		// If the types aren't found to be identical from above, then they have to actually be identical!
		if(!$typesidentical && $this->type != $col->type) return false;

		// Otherwise....
		return true;
	}
}
