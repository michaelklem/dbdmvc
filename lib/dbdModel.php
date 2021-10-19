<?php
/**
 * dbdModel.php :: dbdModel Class File
 *
 * @package dbdMVC
 * @version 1.11
 * @author Don't Blink Design <info@dontblinkdesign.com>
 * @copyright Copyright (c) 2006-2009 by Don't Blink Design
 */

/**
 * dbdMVC Database Table Model
 *
 * <b>Simple Model Example</b>
 * <code>
 * class Widget extends dbdModel
 * {
 * 	const TABLE_NAME = "widgets";
 * 	const TABLE_KEY = "widget_id";
 *
 * 	public function __construct($id = 0)
 * 	{
 * 		parent::__construct(__CLASS__, $id);
 * 	}
 *
 * 	public static function getAll()
 * 	{
 * 		return parent::getAll(__CLASS__);
 * 	}
 * }
 * </code>
 * @package dbdMVC
 * @abstract
 */
abstract class dbdModel
{
	const CONST_TABLE_NAME = 'TABLE_NAME';
	const CONST_TABLE_KEY = 'TABLE_KEY';

	// Memcache constants
	const MEMCACHED_HOST = '127.0.0.1';
	const MEMCACHED_PORT = 11211;

	protected static $memcache = null;//new Memcache();
	
	private $enable_caching = true;
	
	/**
	 * A list of class reflections to limit overhead
	 * @var array ReflectionClass
	 */
	private static $reflections = array();
	/**
	 * Static instance of dbdDB
	 * @var dbdDB
	 */
	protected static $db = null;
	/**
	 * The name of the table
	 * @var string
	 */
	private $table_name = null;
	/**
	 * The key for the table
	 * @var string
	 */
	private $table_key = null;
	/**
	 * Row id
	 * @var integer
	 */
	protected $id = 0;
	/**
	 * Array of fields from row
	 * @var array
	 */
	protected $data = array();
	/**
	 * The constructer's job is to set the table info,
	 * get the db, and call the initializer.
	 * @param string $class
	 * @param integer $id
	 */
	public function __construct($class, $id = 0)
	{
		$this->table_name = self::getConstant($class, self::CONST_TABLE_NAME);
		$this->table_key = self::getConstant($class, self::CONST_TABLE_KEY);
		self::ensureDB();
		$this->id = $id;
		if ($this->id > 0)
			$this->init();
		else
			$this->initFields();
	}
	/**
	 * Select all the fields for this row
	 */
	protected function init_old()
	{
		$sql = "select * from `".$this->table_name."` where `".$this->table_key."` = ?";
		$this->data = self::$db->prepExec($sql, array($this->id))->fetch(PDO::FETCH_ASSOC);
	}

	protected function init()
	{
		$cache_data = null;
		if ($this->allowed_for_caching() && $this->enable_caching)
		{
			$key = $this->table_name."_".$this->id;
			$cache_data = $this->get_cache()->get($key);
			if ($cache_data != null)
			{
				$this->data = $cache_data['data'];
				//dbdLog("WWW Using Cached result for ".$key);
			}
			else
			{
				//dbdLog("WWW Missed Cached result for ".$key);
			}
		}

		if ($cache_data['data'] == null)
		{
			$sql = "select * from `".$this->table_name."` where `".$this->table_key."` = ?";
			//dbdLog("*** DBDMODEL init ".$sql);
			$this->data = self::$db->prepExec($sql, array($this->id))->fetch(PDO::FETCH_ASSOC);
		
			// store in cache
			if ($this->allowed_for_caching())
			{
				$key = $this->table_name."_".$this->id;
				$cache_data = array('id' => $this->id,  'data' => $this->data);
				$this->get_cache()->set($key, $cache_data, MEMCACHE_COMPRESSED, 2);
				//dbdLog("WWW Caching result for ".$key);
			}
		}
	}	
	
	public function disableCaching()
	{
		$this->enable_caching = false;
	}
	
	public function enableCaching()
	{
		$this->enable_caching = true;
	}
	
	/**
	 * Select all the fields names for this table
	 */
	protected function initFields()
	{
		$sql = "describe ".$this->table_name;
		foreach (self::$db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $f)
		{
			if (!isset($this->data[$f['field']]))
				$this->data[$f['field']] = null;
		}
	}
	/**
	 * Save all the fields for this row
	 * @param array $fields
	 */
	public function save($fields = array())
	{
		foreach ($fields as $k => $v)
		{
			if (key_exists($k, $this->data))
			{
				$this->__set($k, $v);
			}
		}
		$sql = "";
		$sql_end = "";
		if ($this->id > 0)
		{
			$sql .= "update";
			$sql_end .= " where `".$this->table_key."` = :".$this->table_key;
		}
		else
		{
			$sql .= "insert into";
		}
		$sql .= " `".$this->table_name."` set ";
		$i = 0;
		foreach ($this->data as $k => $v)
		{
			if ($i++ > 0) $sql .= ",";
			$sql .= "`".$k."` = :".$k;
		}
		$sql .= $sql_end;
//dbdLog('SQL: '.$sql);
foreach($this->data as $key => $value){
//  dbdLog('data: key: '.$key.' = '.$value);
}
		self::$db->prepExec($sql, array_merge(array($this->table_key => $this->id), $this->data));

		// remove cached data 
		if ($this->allowed_for_caching())
		{
			$key = $this->table_name."_".$this->id;
			$this->get_cache()->delete($key);
			$cache_data = array('id' => $this->id,  'data' => $this->data);
			$this->get_cache()->set($key, $cache_data);
			//dbdLog("WWW REFRESHED CACHE ".$key);
		}
		
		if ($this->id == 0)
			$this->id = self::$db->lastInsertId($this->table_name);
		$this->init();
	}
	/**
	 * Delete this row
	 */
	public function delete()
	{
		$sql = "delete from `".$this->table_name."` where `".$this->table_key."` = ?";
		dbdLog('delete sql: '.$sql);
		self::$db->prepExec($sql, array($this->id));
		$this->id = 0;
	}
	/**
	 * Get a count of all rows from this table
	 * @param string $class
	 * @param array $table_keys
	 * @return array dbdModel
	 */
	public static function getCount($class, $table_keys = array())
	{
		self::ensureDB();
		$tmp = array();
		$sql = "select count(1) from `".self::getConstant($class, self::CONST_TABLE_NAME)."`";
		$sql .= self::buildWhereClause($table_keys);
		return self::$db->prepExec($sql, $table_keys)->fetchColumn();
	}
	/**
	 * Get a count of all rows from this table
	 * @param string $class
	 * @param array $table_keys
	 * @return array dbdModel
	 */
	public static function getCountBySQL($class, $sql)
	{
		self::ensureDB();
		$tmp = array();
		return self::$db->prepExec($sql)->fetchColumn();
	}
	/**
	 * Get all rows from this table
	 * @param string $order
	 * @param string $limit
	 * @param boolean $ids_only
	 * @return array dbdModel
	 */
	public static function getAll($class, $table_keys = array(), $order = null, $limit = null, $ids_only = false)
	{
		self::ensureDB();
		$tmp = array();
		$sql = "select `".self::getConstant($class, self::CONST_TABLE_KEY)."` from `".self::getConstant($class, self::CONST_TABLE_NAME)."`";
		$sql .= self::buildWhereClause($table_keys);
		if ($order !== null)
			$sql .= " order by ".$order;
		if ($limit !== null)
			$sql .= " limit ".$limit;
		foreach (self::$db->prepExec($sql, $table_keys)->fetchAll(PDO::FETCH_COLUMN, 0) as $id)
		{
			$tmp[] = $ids_only ? $id : self::getReflection($class)->newInstance($id);
		}
		return $tmp;
	}
	/**
	 * Get all rows from this table using the passed sql statement
	 * @param string $sql
	 * @param boolean $ids_only
	 * @return array dbdModel
	 */
	public static function getAllBySQL($class, $sql, $ids_only = false)
	{
		self::ensureDB();
		$tmp = array();
		foreach (self::$db->prepExec($sql)->fetchAll(PDO::FETCH_COLUMN, 0) as $id)
		{
			$tmp[] = $ids_only ? $id : self::getReflection($class)->newInstance($id);
		}
		return $tmp;
	}
	/**
	 * Build where clause for count and getAll methods
	 * @param array $table_keys
	 * @return string
	 */
	private static function buildWhereClause(&$table_keys = array())
	{
		$sql = "";
		$i = 0;
		foreach ($table_keys as $k => $v)
		{
			$sql .= " ".($i++ > 0 ? "and" : "where")." ";
			$type = dbdDB::COMP_EQ;
			if (!is_array($v))
			{
				$v = array($v);
			}
			if (key_exists(dbdDB::COMP_TYPE, $v))
			{
				$type = $v[dbdDB::COMP_TYPE];
				unset($v[dbdDB::COMP_TYPE]);
			}
			switch ($type)
			{
				case dbdDB::COMP_LIKE:
					$sql .= "`".$k."` like :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_NLIKE:
					$sql .= "`".$k."` not like :".$k;
					$table_keys[$k] = $v[0];
					break;
//				case dbdDB::COMP_IN:
//					$sql .= "`".$k."` in(:".$k.")";
//					break;
//				case dbdDB::COMP_NIN:
//					$sql .= "`".$k."` not in(:".$k.")";
//					break;
				case dbdDB::COMP_BETWEEN:
					$sql .= "`".$k."` between :".$k."__0 and :".$k."__1";
					unset($table_keys[$k]);
					$table_keys[$k.'__0'] = $v[0];
					$table_keys[$k.'__1'] = $v[1];
					break;
				case dbdDB::COMP_NBETWEEN:
					$sql .= "`".$k."` not between :".$k."__0 and :".$k."__1";
					unset($table_keys[$k]);
					$table_keys[$k.'__0'] = $v[0];
					$table_keys[$k.'__1'] = $v[1];
					break;
				case dbdDB::COMP_GTEQ:
					$sql .= "`".$k."` >= :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_GT:
					$sql .= "`".$k."` > :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_LTEQ:
					$sql .= "`".$k."` <= :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_LT:
					$sql .= "`".$k."` < :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_NEQ:
					$sql .= "`".$k."` != :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_ISNOT:
					$sql .= "`".$k."` is not :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_NULLEQ:
					$sql .= "`".$k."` <=> :".$k;
					$table_keys[$k] = $v[0];
					break;
				case dbdDB::COMP_EQ:
				default:
					$sql .= "`".$k."` = :".$k;
					$table_keys[$k] = $v[0];
					break;
			}
		}
		return $sql;
	}
	/**
	 * Return the Reflection of a class
	 * @param string $class
	 * @return ReflectionClass
	 */
	private static function getReflection($class)
	{
		if (!key_exists($class, self::$reflections))
			self::$reflections[$class] = new ReflectionClass($class);
		return self::$reflections[$class];
	}
	/**
	 * Return the value of a class constant using Reflection
	 * @param string $class
	 * @param string $constant
	 * @return mixed
	 */
	private static function getConstant($class, $constant)
	{
		if (!self::getReflection($class)->hasConstant($constant))
			throw new dbdException($class."::".$constant." not defined!");
		return self::getReflection($class)->getConstant($constant);
	}
	/**
	 * Make sure we have an instance of the db
	 */
	public static function ensureDB()
	{
		if (self::$db === null)
			self::$db = dbdDB::getInstance();
	}
	/**
	 * Get the database object
	 * @return dbdDB
	 */
	public static function getDB()
	{
		return self::$db;
	}
	/**
	 * Set the database object
	 * @param dbdDB $db
	 * @return dbdDB
	 */
	public static function setDB($db)
	{
		return self::$db = $db;
	}
	/**
	 * Get the row id
	 * @return integer
	 */
	public function getID()
	{
		return $this->id;
	}
	/**
	 * Get the fields for this row
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}
	/**
	 * Magic function for setting field values
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		$this->data[$name] = $value;
	}
	/**
	 * Magic function for getting field values
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}
	/**
	 * Magic function to check if a field is set
	 * @param string $name
	 * @return boolean
	 */
	public function __isset($name)
	{
		return isset($this->data[$name]);
	}
	/**
	 * Magic function to unset a field
	 * @param string $name
	 */
	public function __unset($name)
	{
		unset($this->data[$name]);
	}
	/**
	 * Magic function allows calling of extra magic functions for testing field values.
	 * Inlcudes: has, is, get, and set.
	 * @throws dbdException
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		if (preg_match("/^([a-z]{2,3})([A-Z][a-zA-Z0-9]+)$/", $name, $tmp))
		{
			$var = str_replace(" ", "_", strtolower(preg_replace("/([^A-Z]{1})([A-Z]{1})/", "$1 $2", $tmp[2])));
			switch ($tmp[1])
			{
				case 'has':
					return isset($this->$var) && !empty($this->$var);
				case 'is':
					return (isset($this->$var) && (count($args) ? $this->$var == $args[0] : $this->$var == true));
				case 'get':
					return $this->$var;
				case 'set':
					return $this->$var = (is_array($args) && isset($args[0])) ? $args[0] : null;
			}
		}
		throw new dbdException(get_class().": Method ('".$name."') not found!");
	}

	private function allowed_for_caching()
	{
		$allowed = false;
		if ($this->table_name == 'users'
			|| $this->table_name == 'schools'
/*
			|| $this->table_name == 'galleries'
			|| $this->table_name == 'gallery_images'
*/
			)
		{
			$allowed = true;
		}
		
		return $allowed;
	}

	private function get_cache()
	{		
		if (self::$memcache == null)
                //if (true)
		{
			self::$memcache = memcache_connect(self::MEMCACHED_HOST, self::MEMCACHED_PORT);
			self::$memcache->flush();
		}
		return self::$memcache;
	}
}
?>
