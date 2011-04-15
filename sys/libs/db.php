<?php
/**
 * Database Manager
 * This is the most basic attempt to make a database manager, it's more like a 
 * wrapper really.
 *
 * @version r2 [2011|APR|14]
 *
 * @todo do some sort of active record to speed up database development.
 *		 look at master, for reference. [did some worke there].
 * @todo These three methods should reside on Core, after all they're very basic.
 */
abstract class DB extends Library {

	public static $debug = false;

	/**
	 * Database Loader
	 * Returns a valid instance of a database, if nothing specified, use memmory
	 *
	 * @param [string] $path The Database path.
	 * @param [object] $instance Added automatically by the Instance class.
	 *
	 * @return [object:reference] pseudo Reflection class of this Class.
	 */
	public static function &load($path = false, $instance = null){
		# just in case someone is fool enough to cause recursion.
		if ($instance) return $instance;
		# If not a valid path specified, generate one for the app.
		# this should issue a warning of some sort.
		if (!$path || stripos($path, 'memory') === false && !file_exists($path))
			$path = TMP.strtolower(APPNAME).'.db';
		# right now only the sqlite driver will be available.		
		try { 
			$dbo = new PDO('sqlite:'.$path);
			$dbo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		} catch (PDOException $e){ 
			parent::error('Unable to load the Database.');
		}
		# wrap the database manager in our reflection instance and return it.
		$instance = new dbInstance($dbo);
		return $instance;
	}


	/**
	 * SQL Executor
	 * As the name implies, executes as SQL statement and returns the number of
	 * affected rows.
	 *
	 * @param	[string]	SQL to execute. you cannot execute SELECT commands,
	 *						it would generate an error.
	 *
	 * @return [int] The number of affected queries.
	 */
	public static function exec($sql = ''){
		$args = func_get_args();
		list($sql, $instance) = self::arguments($args);	
		try {
			if (stripos($sql,'select') !== false)
				throw new PDOException('SELECT connot be used in exec context.');
			$exec = $instance->exec($sql);
		} catch( PDOException $e ){
			return self::$debug?  parent::error($e->getMessage()) : 0;
		}
		return (int) $exec;
	}

	/**
	 * SQL Queries
	 * Plain and simple, make a query return results. 
	 *
	 * @param	[string] 			$sql	SQL to query.
	 * @param	[mixed:infinite]	$rep	Replacements to be used a la printf.
	 *
	 * @return	[array]	Array of results.
	 */
	public static function query($sql = ''){
		$args = func_get_args();
		list($sql, $instance) = self::arguments($args);
		try {
			$qry = null;
			$qry = $instance->query($sql);
		} catch( PDOException $e ){
			return self::$debug?  parent::error($e->getMessage()) : array();
		}
		return $qry->fetchAll();
	}


	private static function arguments(&$args){
		$sql = array_shift($args);
		$ins = array_pop($args);
		if (empty($sql) || !is_string($sql)) parent::error('Invalid SQL.');
		if (!$ins instanceof PDO) parent::error('Missing DB instance.');
		# format sql with remaining arguments.
		$sql = sqlite_escape_string( vsprintf($sql, $args) );
		return array(&$sql, &$ins);
	}
}

class dbInstance extends Instance {}