<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SQLBuilder
 *
 * @author powellc
 */
class SQLBuilder {
	protected $_tables = array();

	protected $_wheres = array();

	protected $_limit = false;

    public function __construct(){

	}

	public function query(){
		// Extend this method in the particular builder type.
	}

	public function execute(){
		return DB::Execute($this->query());
	}

	public function limit($limit){
		$this->_limit = $limit;
	}
	public function getLimit(){
		return $this->_limit;
	}

	public function from($table){
		$this->_tables[] = $table;

		// Ensure no duplicate entries.
		$this->_tables = array_unique($this->_tables);

		// Allow chaining
		return $this;
	}

	public function where($where){
		if($where === null || $where === false){
			// Clear out the wheres.
			$this->_wheres = array();
			return;
		}
		if(is_array($where)){
			// Iterate over each one and see if they key is numeric or not.
			// If numeric, assume a structure of array('something = this', 'something = that');
			// else assume a structure of array('something' => 'this', 'something' => 'that');
			// The latter is sanitized automatically.
			foreach($where as $k => $v){
				if(is_numeric($k)) $this->_wheres[] = $v;
				else $this->_wheres[] = "`$k` = " . DB::qstr ($v);
			}
		}
		else{
			// It's not an array, but still add support for strings such as "something = ?" (where the ? is sanitized automatically)
			if(func_num_args() > 1 && strpos($where, '?')){
				foreach(func_get_args() as $k => $a){
					if($k == 0) continue; // First argument already taken care of.
					$str = DB::qstr($a);
					// I only want to replace one ?, but don't want to use the overhead of regex's.
					$where = substr_replace($where, $str, strpos($where, '?'), 1);
				}
			}
			$this->_wheres[] = $where;
		}

		// Ensure no duplicate entries.
		$this->_wheres = array_unique($this->_wheres);

		// Allow chaining
		return $this;
	}
}


class SQLBuilderSelect extends SQLBuilder{

	protected $_selects = array();

	protected $_order = array();

	public function query(){
		$q = "SELECT " . implode(', ', $this->_selects);
		$q .= " FROM " . implode(', ', $this->_tables);
		if(!empty($this->_wheres)) $q .= ' WHERE ' . implode(' AND ', $this->_wheres);
		if(!empty($this->_order)) $q .= ' ORDER BY ' . implode(', ', $this->_order);
		if($this->_limit) $q .= ' LIMIT ' . $this->_limit;
		return $q;
	}

	public function select($select){
		if(is_array($select)) $this->_selects = array_merge($this->_selects, $select);
		else $this->_selects[] = $select;

		// Ensure no duplicate entries.
		$this->_selects = array_unique($this->_selects);

		// Allow chaining
		return $this;
	}

}

class SQLBuilderUpdate extends SQLBuilder{
	protected $_sets = array();

	public function query(){
		$q = "UPDATE " . implode(', ', $this->_tables);
		$q .= " SET " . implode(', ', $this->_sets);
		if(!empty($this->_wheres)) $q .= ' WHERE ' . implode(' AND ', $this->_wheres);
		if($this->_limit) $q .= ' LIMIT ' . $this->_limit;
		return $q;
	}

	public function set($column, $value){
		$this->_sets[] = "`$column` = " . DB::qstr($value);
	}
}

class SQLBuilderInsert extends SQLBuilder{
	protected $_sets = array();

	public function query(){
		$q = "INSERT INTO " . implode(', ', $this->_tables);
		$q .= " ( " . implode(', ', array_keys($this->_sets)) . " )";
		$q .= " VALUES ";
		$q .= " ( " . implode(', ', array_values($this->_sets)) . " )";
		return $q;
	}

	public function set($column, $value){
		$this->_sets["`$column`"] = DB::qstr($value);
	}
}

class SQLBuilderDelete extends SQLBuilder{
	public function query(){
		$q = "DELETE FROM " . implode(', ', $this->_tables);
		if(!empty($this->_wheres)) $q .= ' WHERE ' . implode(' AND ', $this->_wheres);
		if($this->_limit) $q .= ' LIMIT ' . $this->_limit;
		
		return $q;
	}
}