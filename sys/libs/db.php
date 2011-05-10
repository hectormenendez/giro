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
			error('Unable to load the Database.');
		}
		# wrap the database manager in our reflection instance and return it.
		$instance = new dbInstance($dbo);
		return $instance;
	}

	/**
	 * Return a reference to the original PDO instance, hardcore baby!
	 */
	public static function &PDO(){
		$argv =func_get_arg(0);
		return $argv;
	}


	/**
	 * SQL Query
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
		}
		catch( PDOException $e ){ return self::error($e); }
		return ($qry)? $qry->fetchAll(PDO::FETCH_ASSOC) : false;
	}


	/**
	 * SQL Execute
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
		}
		catch( PDOException $e ){ return self::error($e); }
		
		return (int) $exec;
	}


################################################################ INTERNALS #####

	/**
	 * Arguments Parser
	 * Checks for DB instance and format/escapes SQL.
	 */
	private static function arguments(&$args){
		$sql = array_shift($args);
		$ins = array_pop($args);
		if (empty($sql) || !is_string($sql)) error('Invalid SQL.');
		if (!$ins instanceof PDO) error('Missing DB instance.');
		# format and sanitize sql using remaining args
		# but first check if the user is actually sending the correct number of them..
		$args = (array)$args; # just in case.
		$count = count($args);
		# oh my, I'm proud of this regex.
		$regex = preg_match_all('/(?<!%)%(?!%+)(?:(?:\d+\$)?[bcdeEufFgGosxX])?/', $sql, $match);
		if ($count !== $regex) error("Expecting $regex arguements, got $count. [".@implode(', ',$match[0]).']');
		foreach((array)$args as $k=>$v) $args[$k] = $ins->quote($v);
		$sql = vsprintf($sql, $args);
		return array(&$sql, &$ins);
	}

	/**
	 * PDOException extractor.
	 */
	private static function error(&$exception){
		$e = $exception->getMessage();
		$e = substr($e, strpos($e, ':') +1 );
		return error($e);
	}

}

class dbInstance extends Instance {}