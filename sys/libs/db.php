<?php

/**
 * SQLite[2] Database Management.
 *
 * @version 1.2 [29/SEP/2010]
 * @author	Hector Menendez
 *
 * @log		Multiple database handling added.
 *
 * @todo	active-record like, methods.
 * @todo	relative paths are not being handled correctly.
 * @todo 	self::load(should detect if no database is loaded);
 */
abstract class DB extends Library {

	private static $curr = null;

	public static function &load($path=false, $instance=false){
		# send error if SQLite is not available
		if (!class_exists('SQLiteDatabase')) self::error('Database manager unavailable.');
		# intancing, always returns a new object. static [default] reuses the object.
		if ($instance===true) {
			$sqlite = self::sqlite($path);
			$dbctrl = new DBControl(&$sqlite);
			return $dbctrl;
		} else {
			# if there is already a database loaded use it.
			if (self::$curr instanceof SQLiteDatabase) return self::$curr;
			self::$curr = self::sqlite($path);
			return self::$curr;
		}
	}

	public static function &sqlite($path){
		$sqlite = null;
		try 					{ $sqlite = new SQLiteDatabase($path); 					} 
		catch (Exception $e)	{ self::error('Database could not be initialized.');	}
		return $sqlite;
	}

	/**
	 * Query database.
	 *
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @param	object	$__db	A database object. Instanced version uses this.
	 * @return	array			First row of result.
	 *
	 * @author Hector Menendez
	**/
	public static function query($sql, $type=SQLITE_ASSOC, $__db=null){
		return self::_query(false,$sql,$type,$__db);
	}

	/**
	 * Query: Return first row.
	 *
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @param	object	$__db	A database object. Instanced version uses this.
	 * @return	array			First row of result.
	 *
	 * @author Hector Menendez
	**/
	public static function first($sql=false, $type=SQLITE_ASSOC, $__db=null){
		return self::_query('one',$sql,$type,$__db);
	}

	/**
	 * Execute a query
	 *
	 * @param 	string 	$sql	THe SQL command.
	 * @param	object	$__db	A database object. Instanced version uses this.
	 * @return	bool			true on success.
	 *
	 * @author Hector Menendez
	**/
	public static function execute($sql, $__db=null){
		return self::_query('exe',$sql,$__db);
	}

	/**
	 * Internal Query handler
	 *
	 * @param	string	$cmd 
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @param	object	$db		A database object. Instanced version uses this.
	 * @return	mixed			Result.
	 *
	 * @author Hector Menendez
	**/
	private static function _query($cmd,$sql=false,$type=false,$db=null){
		if (!is_string($sql)) self::error('Invalid Query.');
		if (!$db instanceof SQLiteDatabase) $db = &self::load();
		var_dump($db);
		$sql = sqlite_escape_string($sql);
		# execute query
		if ($cmd == 'exe' && @$db->queryExec($sql,$error) !== false) return true;
		# return an array 
		else if (false !== ($qry = @$db->query($sql,$type,$error))) {
			# only the first element
			if ($cmd == 'one') return $qry->fetch();
			# all elements
			return $qry->fetchAll();
		}
		# invalid query
		self::error("Invalid Query $error");
	}

}

class DBControl extends Instance {}