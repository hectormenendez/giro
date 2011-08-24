<?php
class DB extends Library {

	# Array indexed by column name
	const FETCH_ASSOC  = PDO::FETCH_ASSOC;
	# Array indexed by column numbr as returned by result.
	const FETCH_NUM    = PDO::FETCH_NUM;
	# Anonymous object with property names corresponding to column names		
	const FETCH_OBJ    = PDO::FETCH_OBJ;
	# Array indexed by both column name and 0-indexed as returned by result
	const FETCH_BOTH   = PDO::FETCH_BOTH;
	# Combines FETCH_BOTH & FETCH_OBJ
	const FETCH_LAZY   = PDO::FETCH_LAZY;
	# return only a single requested column from the next row in the result set.
	const FETCH_COLUMN = PDO::FETCH_COLUMN;

	public $instance = null;
	public $driver   = null;
	public $fetching = DB::FETCH_ASSOC;

	private $lastSQL;
	private $lastEXE;
	private $statement = array();
	private $queries   = array();
	private $name      = null;


	/**
	 * Internal instance constructor.
	 *
	 * redirects the original static call to an driver-specific cosntructor.
	 */
	public function &__construct(){
		# a rudimentaty-yet-effective way of making sure the class 
		# won't be constructed from outside.
		$bt = debug_backtrace();
		if ($bt[0]['file']!==__FILE__) error(__CLASS__.' cannot be instanced.');
		$args = func_get_args();
		$type = (string)array_shift($args);
		if (!is_callable(array($this,'construct_'.$type)))
			error(ucwords($type).' is not a valid Database driver.');
		$instance = call_user_func_array(array($this,'construct_'.$type), $args);
		return $instance;
	}

	/**
	 * MYSQL Driver static construct
	 *
	 * @see DB->construct_mysql();
	 */
	public static function mysql($db=false, $password='', $user='root', $host='localhost', $port='3307'){
		return new DB('mysql', $db, $password, $user, $host, $port);
	}

	/**
	 * MYSQL Loader
	 * Returns a valid instance of a mysql database.
	 * 
	 * @param [string] $db       Existing database name.
	 * @param [string] $password Valid Database password         [defaults to empty]
	 * @param [string] $user     Valid Database user             [defaults to root]
	 * @param [string] $host     Valid Hostname for database     [defaults to localhost]
	 * @param [string] $port     Valid Port number fort database [defaults to 3307]
	 */
	private function &construct_mysql(){
		if (
			func_num_args() != 5 || 
			!@list($db,$password,$user,$host,$port) = func_get_args()
		)	error('Invalid mysql arguments');
		if (!is_string($db)) error('A database name must be provided');
		try {
			$this->instance = new PDO(
				'mysql:host='.(string)$host.';port='.(string)$port.';dbname='.$db,
				(string)$user,
				(string)$password,
				array(
					PDO::ATTR_PERSISTENT         => false,
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
				)
			);
			$this->instance->exec('SET CHARACTER SET utf8');
			# show errors on erroneous queries.
			$this->instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e) { $this->error($e); }
		$this->driver = 'mysql';
		$this->name   = $db;
		return $this;
	}

	/**
	 * SQLITE Driver static construct 
	 */
	public static function sqlite($path=false){
		return new DB('sqlite',$path);
	}

	/**
	 * SQLITE Loader
	 * Returns a valid instance of a database, if nothing specified, use memmory
	 *
	 * @param [string] $path The Database path.
	 */
	private function &construct_sqlite(){
		if (func_num_args() != 1 || !@list($path) = func_get_args())
			error('Invalid mysql arguments');
		# If not a valid path specified, generate one for the app.
		# this should issue a warning of some sort.
		if (!is_string($path)) $path = TMP.strtolower(UUID).'.db';
		# right now only the sqlite driver will be available.		
		try { 
			$this->instance = new PDO('sqlite:'.$path);
			$this->instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} 
		catch (PDOException $e) { $this->error($e); }
		$this->driver = 'sqlite';
		return $this;
	}


	/**
	 * Wrapper to retrieve last insertion id's KEY
	 */
	public function lastid(){
		return $this->instance->lastInsertId();
	}

	/**
	 * Execute statement in database
	 *
	 * @see $this->prepare();
	 */
	public function exec(){
		if (!$exed = call_user_func_array(array($this,'prepare'), func_get_args())) return 0;
		return $this->lastEXE;
	}


	/**
	 * Make queries to Database
	 *
	 * @see $this->prepare();
	 */
	public function &query(){
		$array = array();
		if (!$exed = call_user_func_array(array($this,'prepare'), func_get_args())) return $array;
		# if we have that query on cache return it.
		if (isset($this->queries[$this->lastSQL]) && is_object($this->queries[$this->lastSQL]))
			return $this->queries[$this->lastSQL];
		$this->queries[$this->lastSQL] = $exed->fetchAll($this->fetching);
		return $this->queries[$this->lastSQL];
	}

	/**
	 * Preparing statements.
	 *
	 * Provide a common interface for prepared statements for query and exec
	 *
	 * @param [string] $sql       The SQL to execute. 
	 *                            It accepts named and unnamed prepared statements.
	 *                            ie: 'SELECT * FROM table WHERE id=?'
	 *                            or: 'SELECT * FROM table WHERE id=:id'
	 *
	 * @param [mixed]  $Narg      The replacement values for the prepared statement.
	 *                            ie: (string)'1' OR (int)1
	 *                            or in case of named statement: array(':id'=>'1')
	 *
	 * @return [object reference] The prepared and queried object.
	 */
	private function &prepare(){
		if (($num = func_num_args())<1) error('Invalid number of arguments');
		$arg = func_get_args();
		$sql = array_shift($arg);
		if (empty($sql) || !is_string($sql)) error('Invalid SQL.');
		try {
			# if there's already a cached version of this preparation, return it.
			if (!isset($this->statement[$sql]) || !is_object($this->statement[$sql]))
				$this->statement[$sql] = $this->instance->prepare($sql);
			if ($num > 1 && (isset($arg[0]) && is_array($arg[0])) && $num > 2)
				error('Only one argument required when using an array for replacement statements.');
			elseif (isset($arg[0]) && is_array($arg[0])) $arg = $arg[0];
			$this->lastEXE = $this->statement[$sql]->execute($arg);			
		}
		catch (PDOException $e) { $this->error($e); }
		$this->lastSQL = $sql;
		return $this->statement[$sql];
	}

	/**
	 * Export SQL Structure and Data.
	 *
	 * @param [mixed] $path Save output to path or returns it.
	 *
	 * @note only works for mysql driver.
	 * @todo add support for sqlite.
	 */
	public function export($path=false){
		if ($this->driver != 'mysql')
			error('Support for exporting databases other than mysql is not implemented yet.');
		function col($a){ return current($a); }
		function row($s){ return addslashes((string)$s); }
		$backup = '';
		# Table structure
		foreach( $this->query('SHOW TABLES') as $table){
			$table = current($table);
			foreach ($this->query('SHOW CREATE TABLE '.$table) as $sql)
				$backup .= "DROP TABLE IF EXISTS `$table`;\n".next($sql).";\n\n";
			$rows = '';
			foreach($this->query("SELECT * FROM $table") as $row)
				$rows.="\n".'("'.implode('","', array_map('row', $row)).'"),';
			# continue only if data found
			if (!$rows) continue;
			# join column names into a string
			$cols = '(`'.implode('`,`', array_map('col',$this->query("SHOW COLUMNS FROM $table"))).'`)';
			$backup .= "INSERT INTO `$table` $cols VALUES ".substr($rows, 0,-1).";\n\n";
		}
		if (!is_string($path)) return $backup;
		return file_put_contents($path, $backup);
	}

	/**
	 * Import external SQL
	 * It only wraps a file_get_contents call.
	 */
	public function import($path=false){
		if (!is_string($path) || !file_exists($path))
			error('Could not import, missing file.');
		$sql = file_get_contents($path);
		# no preparation needed, execute directly from instance.
		return $this->instance->exec($sql);
	}

	/**
	 * Check if current database has any tables.
	 */
	public function is_empty(){
		switch($this->driver){
			# good ol' mysql
			case 'mysql': $sql =
              "SELECT count(*)
                 FROM information_schema.tables
                WHERE table_type = 'BASE TABLE' AND table_schema ='{$this->name}'";
            break;
            # sqlite is so easy it hurts-
            case 'sqlite': $sql =
              "SELECT count(*)
                 FROM sqlite_master WHERE sqlite_master.type = 'table'";
            break;
            default: error('Operation not yet implemented');
		}
		if ((int)$this->instance->query($sql)->fetchColumn()===0) return true;
		return false;
	}

	/**
	 * INSERT statement shortcut
	 * @param req string $table    Table name.
	 * @param req  mixed $selector - string column name, value must be set.
	 *                             - associative array, keys act as column names.
	 * @param opt  mixed $value    Only set when $selector is string.
	 *
	 * @working 2011/AUG/24 15:21
	 * @created 2011/AUG/24 14:25
	 */
	 public function insert($table=false, $selector=false, $value=false){
	 	if (
		 	!is_string($table)                                           ||
		 	# selector can only be array or string
		 	(!is_string($selector) && !is_array($selector))              ||
		 	# selector is string, must contain a value
		 	(is_string($selector) && empty($value))                      ||
		 	# value set, selector must only specify one column
		 	(!empty($value) &&  
				(!is_string($selector) || strpos($selector,',')!==false)
			)                                                            ||
		 	# selector is array, must have values
		 	(is_array($selector) && empty($selector))                    ||
		 	# selector is array, no value must be set
		 	(is_array($selector) && !empty($value))                      ||
		 	# selector is array, it must be associative
		 	(is_array($selector) && array_keys($selector) === range(0,count($selector)-1))
		) error('Bad arguments for INSERT statement');
		# all clear for work
		# if selecto is string convert it to "right" format.
		if (is_string($selector)) $selector = array($selector => $value);
		$sql = "INSERT INTO $table ("
			.implode(',', array_keys($selector)).") VALUES ("
			.implode(',', array_fill(0, count($selector),'?')).")";
		return $this->exec($sql, array_values($selector));
	 }


 	/**
 	 * SELECT statement shortcut
 	 * 
 	 * @param req string $table     Table Name
 	 * @param opt string $selector  Column selector, Defaults to *.
 	 * @param opt string $condition Conditions to apply.
 	 * @param opt  mixed $values    Replacement values for prepared queries.
 	 *
 	 * @return mixed - Query result, varies depending on default fetching style.
 	 *               - First Column array, If only one column is specified.
 	 *               - First Row if a LIMIT 1 is specified.
 	 *
 	 * @working 2011/AUG/23 14:23
 	 * @created 2011/AUG/24 12:01
 	 */
 	public function select($table=false, $selector=false, $condition='', $values=null){
 		if (!$selector) $selector = '*';
 		if (
	 		!is_string($table)    || 
	 		!is_string($selector) ||
	 		!empty($condution) && !is_string($condition)
	 	) error('Bad arguments for SELECT statement');
	 	# store current fetching style
	 	$fetching = $this->fetching;
	 	# start building statment.
	 	$sql = "SELECT $selector FROM $table";
	 	# selector only has one column? return only that.
	 	if (trim($selector) != '*' && strpos($selector, ',')===false)
	 		$this->fetching = DB::FETCH_COLUMN;
	 	# there are no conditions: query, restore original fetching and return
	 	if (empty($condition)) return $this->queryandfetch($sql, $fetching);
	 	# do we really need to add a WHERE statement? 
	 	if (
		 	stripos($condition, 'WHERE') !== false ||
		 	preg_match('%=|<|>|!|~%', $condition)   # relational operators
	 	)    $sql .= " WHERE $condition";
	 	else $sql .= " $condition";
		# extract values, and do a normal prepared query.
		$values = array_slice(func_get_args(),3);
		$qry = $this->query($sql, $values);
		# if the SQL is limited to one, just return first row,col.
		if (stripos($condition, 'LIMIT 1')!==false) return array_shift($qry);
		return $qry;
 	}

 	/**
 	 * Queries given SQL, fetches with currently set style 
 	 * and optionally sets a new one. [or the original one, for that matter]
 	 *
 	 * @working 2011/AUG/24 13:19
 	 * @created 2011/AUG/24 13:15
 	 */
 	private function queryandfetch($sql, $fetching=false){
 		if ($fetching === false) $fetching = $this->fetching;
	 	$qry = $this->instance->query($sql)->fetchAll($this->fetching);
	 	$this->fetching = $fetching;
	 	return $qry;
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