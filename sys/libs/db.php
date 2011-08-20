<?php
class DB extends Library {

	public $instance = null;
	public $driver   = null;

	private $lastSQL;
	private $lastEXE;
	private $statement = array();
	private $queries   = array();

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
		if (!$path || stripos($path, 'memory') === false && !file_exists($path))
			$path = TMP.strtolower(APP_NAME).'.db';
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
		$this->queries[$this->lastSQL] = $exed->fetchAll(PDO::FETCH_ASSOC);
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
		return $this->exec($sql);
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