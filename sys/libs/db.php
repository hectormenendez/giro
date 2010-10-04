<?php

/**
 * SQLite[2] Database Management.
 *
 * @version 1.3 [01/OCT/2010]
 * @author	Hector Menendez
 *
 * @log		create_database method added.
 * @log		fixed a bug in execute()
 *
 * @todo	active-record like, methods.
 * @todo	relative paths are not being handled correctly.
 * @todo 	self::load(should detect if no database is loaded);
 */
abstract class DB extends Library {

	private static $curr = null;
	private static $etypes = array('INTEGER','TEXT','DATE','FLOAT','VARCHAR(?:\(\d+\))*','BOOLEAN');
	private static $eprops = array('DEFAULT\s+[^\s]+','UNIQUE','AUTOINCREMENT','NOT NULL','PRIMARY KEY');

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
	public static function query($sql, $type=false, $error=_error, $__db=null){
		return self::_query(false,$sql, $type, $error, $__db);
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
	public static function first($sql=false, $type=false, $error=_error, $__db=null){
		return self::_query('one', $sql, $type, $error, $__db);
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
	public static function execute($sql=false, $error=_error, $__db=null){
		return self::_query('exe', $sql, false, $error, $__db);
	}

	public static function table_exists($name=false, $error=_error, $__db=null){
		if (!is_string($name)) self::error('Invalid table name.');
		if (!$__db instanceof SQLiteDatabase) $__db = &self::load();
		$e = __METHOD__;
		$sql = 'SELECT name FROM sqlite_master WHERE name="%s"';
		if (!$sql=self::first(sprintf($sql, $name),false,false,$__db)) return false;
		return true;
	}

	/**
	 * Shortcut for creating tables.
	 *
	 * @param string	$name	The table name.
	 * @param string	$elem 	An array of fields with their declarations.
	 * @param bool		$error	Whether to show an error or not.
	 * @param object	$__db	The database Object.
	 *
	 * @return bool				true on success [error on failure]
	 * @author Hector Menendez
	**/
	public static function table_create($name=false, $elem=false, $error=_error, $__db=null){
		if (!$__db instanceof SQLiteDatabase) $__db = &self::load();
		$e = __METHOD__;
		if (!is_string($name)) self::error("[$e] Invalid table name.");
		if (!is_array($elem) || !count($elem)) self::error("[$e] Invalid table elements.");
		$pkset = false;
		$sql = '';
		foreach($elem as $key=>$def){
			if (is_numeric($key)) self::error("[$e] Invalid element name.");
			# remove unnecesary white spaces.
			$def = trim(str_replace('  ',' ',$def));
			# there must be only one 'type' declaration. [keep a copy of the sql without the match]
			# regex: 	word must be at the beginning of the line
			#			word must have one or more spaces after it or be at the end of line.
			$copy = self::_verify($def,self::$etypes,'^','(\s+|$)');
			if ($copy===false) self::error("[$e] Invalid or missing type for '$key'.");
			# do this only if element have more than a type declaration
			if (strpos($def,' ')) {
				# traverse string all restant words must be valid
				do {
					# regex:	word can be at the beginning of line or having one or more prepending space.
					#			or can be at the end of the line or have one or more appendingspace.
					$copy = self::_verify($copy." ", self::$eprops,'(^|\s+)','(\s+|$)');
					if ($copy===false) self::error("[$e] Invalid property given for '$key'.");
					$copy = trim($copy);
				} while(!empty($copy));
			}
			# if we reach here, our SQL should have valid syntax however, there are things still to do.
			$copy = strtoupper($def);
			if (($pos=strpos($copy,'PRIMARY KEY'))!==false){
				# for some reason, PRIMARY KEY must be declared at the end of the sql statement.
				# so remove it from original declaration and trigger a boolean to set it at the end.
				# also, we can only allow one primary key for table, so check for that too.
				if ($pkset) self::error("[$e] Only one Primary Key is allowed per table.");
				$def = substr($def, 0, $pos).substr($def,$pos+11);
				$pkset = $key;
			}
			if (($pos=strpos($copy,'DEFAULT'))!==false){
				# escape default value declaration.
				$def = preg_replace_callback (
					"/DEFAULT\s+([^\s]+)/i",
					create_function('$match', 'return \'DEFAULT "\'.sqlite_escape_string($match[1]).\'"\';'),
					$def
				);
			}
			$sql .= '"'.sqlite_escape_string($key).'" '.trim($def).',';
		}
		# add PK declaration or remove the last comma.
		if ($pkset) $sql.="PRIMARY KEY (\"$pkset\")"; else $sql = substr($sql, 0, -1);
		$sql = "CREATE TABLE $name ($sql);";
		if (@$__db->queryExec($sql,$error) === true) return true;
		return self::error("[$e] Could not create Table. $error");
	}

#-------------------------------------------------------------------------------------------------------------

	/**
	 * Internal Query handler
	 *
	 * @param	string	$cmd 
	 * @param 	string	$sql	The SQL command.
	 * @param	const	$type	How to index the result? SQLITE_ASSOC, SQLITE_NUM or SQLITE_BOTH; 
	 * @param	object	$__db	A database object. Instanced version uses this.
	 * @return	mixed			Result.
	 *
	 * @author Hector Menendez
	**/
	private static function _query($cmd, $sql=false, $type=SQLITE_ASSOC, $error=_error, $__db=null){
		if (!is_string($sql)) self::error('Invalid Query.');
		if (!$__db instanceof SQLiteDatabase) $__db = &self::load();
		if (!$type) $type = SQLITE_BOTH;
		$sql = sqlite_escape_string($sql);
		# execute query
		if ($cmd == 'exe' && @$__db->queryExec($sql,$error) !== false) return true;
		# return an array 
		else if (false !== ($qry = @$__db->query($sql,$type,$error))) {
			# only the first element
			if ($cmd == 'one') return $qry->fetch();
			# all elements
			return $qry->fetchAll();
		}
		# invalid query
		return self::error(__METHOD__." Invalid Query: $error");
	}

	/**
	 * Verify if given string is contained inside of an array of keys. [regex]
	 *
	 * @param	string 	$string 	The string we want to probe.
	 * @param	array 	$array 		An array containing the strings we wanna make sure exist. [regex]
	 * @param	string	$pre		Prepend regex to every element in array.
	 * @param	strint	$pos		Append regex to every element in array.
	 *
	 * @return	mixed				false if no matches found, string minus match otherwise.
	 * @author Hector Menendez
	**/
	private static function _verify($str, $arr,$pre=null,$pos=null){
		$found = false;
		foreach($arr as $key){
			$regx = ($pre?$pre:'').$key.($pos?$pos:'');
			if (preg_match($rx="/{$regx}/i",$str)){
				$str = preg_replace($rx, ' ', $str);
				$found = true;
				break;
			}
		}
		return $found? $str : false;
	}

}

class DBControl extends Instance {}