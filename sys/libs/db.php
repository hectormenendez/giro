<?php

/**
 * SQLite[2] Database Management.
 *
 * @package	default
 * @author	Hector Menendez
 *
 * @todo	active-record like, methods.
 * @todo	multiple database handling.
 * @todo	relative paths are not being handled correctly.
 * @todo	multiple database type support.
 * @todo 	self::load(should detect if no database is loaded);
 */
abstract class DB extends Library {

	private static $db = null;

	public static function &load($path=false){
		if (self::$db instanceof SQLiteDatabase) return self::$db;
		#if (!is_string($path) || !file_exists($path)) self::error('Invalid Database.');
		if (!class_exists('SQLiteDatabase')) self::error('Database manager unavailable.');
		if (!self::$db = new SQLiteDatabase($path)) self::error('Database could not initialize.');
		return self::$db;
	}

	/**
	 * Query database.
	 *
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @return	array			First row of result.
	 *
	 * @author Hector Menendez
	**/
	public static function query($sql=false, $type=SQLITE_ASSOC){
		return self::_query(false,$sql,$type);
	}

	/**
	 * Query: Return first row.
	 *
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @return	array			First row of result.
	 *
	 * @author Hector Menendez
	 */
	public static function first($sql=false,$type=SQLITE_ASSOC){
		return self::_query('one',$sql,$type);
	}

	/**
	 * Execute a query
	 *
	 * @param 	string 	$sql	THe SQL command.
	 * @return	bool			true on success.
	 *
	 * @author Hector Menendez
	 */
	public static function execute($sql){
		return self::_query('exe',$sql);
	}

	/**
	 * Internal Query handler
	 *
	 * @param	string	$cmd 
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @return	mixed			Result.
	 *
	 * @author Hector Menendez
	 */
	private static function _query($cmd,$sql=false,$type=false){
		if (!is_string($sql)) self::error('Invalid Query.');
		$db = self::load();
		$sql = sqlite_escape_string($sql);
		# execute query
		if ($cmd == 'exe' && @$db->queryExec($sql,$error) !== false) return true;
		# return an array 
		else if (false !== ($qry = @$db->query($sql,$type,$error))) {
			# only the first element
			if ($cmd = 'one') return $qry->fetch();
			# all elements
			return $qry->fetchAll();
		}
		# invalid query
		self::error($error);
	}

}