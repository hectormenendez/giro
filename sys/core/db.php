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
	 *
	 * @updated 2011/AGO/26 08:02 replaced manual check for Library::samefile()
	 */
	public function &__construct(){
		# Allow only this file to construct the class.
		if (!parent::samefile()) error('DB cannot be instanced directly.');
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
		$this->driver = 'mysql';
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
		$this->driver = 'sqlite';
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
 	 * SELECT statement shortcut
 	 * 
 	 * @param req string $table     Table Name
 	 * @param opt string $selector  Column selector, Defaults to *.
 	 * @param opt string $condition Conditions to apply.
 	 * @param opt  mixed $values    Replacement values for condition.
 	 *
 	 * @return mixed - Query result, varies depending on default fetching style.
 	 *               - First Column array, If only one column is specified.
 	 *               - First Row if a LIMIT 1 is specified.
 	 *
 	 * @updated 2011/AUG/27 14:03   Fixed a bug; fetching style was not being restored.
 	 * @updated 2011/AUG/24 17:17   Moved condition checking to its own method.
 	 * @working 2011/AUG/24 14:23
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
	 	if ($this->is_condition($condition)) 
	 		 $sql .= " WHERE $condition";
	 	else $sql .= " $condition";
		# extract values, and do a normal prepared query.
		$values = array_slice(func_get_args(),3);
		$qry = $this->query($sql, $values);
		# restore original fetching style;
		$this->fetching = $fetching;
		# if the SQL is limited to one, just return first row,col.
		if (stripos($condition, 'LIMIT 1')!==false) return array_shift($qry);
		return $qry;
 	}
 
 	/**
	 * INSERT statement shortcut
	 *
	 * @param req string $table     Table name.
	 * @param req  array $column    Associative array or Array of associative arrays, 
	 *                              keys act as column names.
	 * @param opt   bool $replace   if a duplicate is found... replace it?
	 *                              if false, an error will be triggered on duplicate.
	 *
 	 * @return bool Execution status.
 	 *
 	 * @note   For some reason, if you use a large set of rows, an error will 
 	 *         trigger, it seems that innoDB has to do with it.
 	 *         I've read that changing a setting in mySQL config can fix it:
 	 *         /etc/my.cfg  innodb_lock_wait_timeout = 50 [increase it]
 	 *
 	 *         Since the whole idea of this framework is to work in a shared
 	 *         environment, changing settings like those will be impossible.
 	 *         so, until I figure this out, I would recommend using this method
 	 *         for small/single inserts.
 	 *
 	 *         But please, if you read this, stress-test this method and let me know.
 	 *         if it works.
 	 *         ----- UPDATE -----
 	 *         It seems that restarting my server was enough for the error to 
 	 *         appear, so it might be that a bad insert when developing was the
 	 *         one to blame for it.
 	 *         Anyways, I'm leaving this message until I debug a little more, 
 	 *         also, this needs to be tested in a production environment.
 	 *         I'll remove this, once I'm sure it was a human error an not 
 	 *         a memory leak caused by my poor programming skills.
 	 *
 	 * @updated 2011/SEP/16 03:58   Updated note.
 	 * @updated 2011/SEP/16 03:15   Added note.
	 * @updated 2011/SEP/16 01:33   Insert multiple rows enabled.
	 *                              Enhanced row replacement. [untested]
	 * @updated 2011/AUG/29 15:10   Added Update fallback,
	 * @updated 2011/AUG/24 19:14   Column will be required to be an array.
	 * @updated 2011/AUG/24 17:47   Renamed $selector to $column.
	 * @updated 2011/AUG/24 17:36   Moved selector checking to its own method.
	 * @working 2011/AUG/24 15:21
	 * @created 2011/AUG/24 14:25
	 */
	public function insert($table=false, $column=false, $replace=false){
	 	if (!is_string($table)                    || 
		 	!($column = $this->column_args($column))
		) error('Bad arguments for INSERT statement');
		if (stripos($table, 'INSERT')!==false) return false;
		# all arrays must share the same key names.
		$key = array_keys(current($column));
		# populate sql with key names.
		$sql = "INSERT INTO `$table` (".implode(',',array_map(function($a){return "`$a`";},$key)).") VALUES ";
		# value set, with replacement placemarks.
		$row = '('.implode(',', array_fill(0, count($key),'?')).')';
		$rows = array();
		$vals = array();
		// now, the value generation.
		foreach ($column as $col) {
			if (array_keys($col) !== $key) return error('All arrays must have the same key set.');
			$rows[] = $row;
			$vals   = array_merge($vals, array_values($col));
		}
		$sql .= implode(', ', $rows).' ';
		// if no replacing is needed, just execute sql now.
		if (!$replace) return $this->exec($sql, $vals);
		// prepare on duplicate statement
		$prikey = $this->primary_key($table);
		$sql .= "ON DUPLICATE KEY UPDATE $prikey=LAST_INSERT_ID('$prikey'), ";
		$sets = array();
		foreach($key as $k){
			if ($k == $prikey) continue;
			$sets[] = "`$k`=VALUES(`$k`)";
		}
		$sql .= implode(', ', $sets);
		return $this->exec($sql, $vals);
	}

	/**
	 * UPDATE statement shortcut
	 *
	 * @param req string $table     Table name.
	 * @param req  array $column    Associative array, keys act as column names.
 	 * @param opt string $condition Conditions to apply.
 	 * @param opt  mixed $value     Replacement values for condition.
 	 *
 	 * @return bool Execution status.
 	 *
 	 * @updated 2011/SEP/16 01:37   Column_args now returns an array of arrays;
 	 *         	                    But we'll only use the first one.
	 * @working 2011/AUG/24 20:26
	 * @created 2011/AUG/24 16:37
	 */
	public function update($table=false, $column=false, $condition=false, $value=false){
	 	if (!is_string($table)                    || 
		 	!($column = $this->column_args($column))
		) error('Bad arguments for UPDATE statement');
		if (stripos($table, 'UPDATE')!==false) return false;
		$column = current($column);
		$keys = implode(',',array_map(function($a){ return $a.'=?';}, array_keys($column)));
		$sql = "UPDATE $table SET $keys";
		# if no condition is  given, execute query now.
		if (!$this->is_condition($condition)) return $this->exec($sql, array_values($column));
		$sql .= " WHERE $condition";
		# is the user sending replacing values?  merge them.
		$value = array_slice(func_get_args(),3);
		return $this->exec($sql, array_merge(array_values($column), $value));
	}

	/**
	 * DELETE statement shortcut
	 *
	 * @param req string $table     Table name.
 	 * @param opt string $condition Conditions to apply.
 	 * @param opt  mixed $value     Replacement values for condition.
 	 *
 	 * @return bool Execution status.
 	 *
 	 * @working 2011/AUG/24 20:54
	 * @created 2011/AUG/24 20:44
	 */
	public function delete($table=false, $condition=false, $value=false){
		if (!is_string($table)                 || 
			stripos($table, 'DELETE') !==false ||
			!$this->is_condition($condition)
		)	error('Bad arguments for DELETE statments');
		# plain and simple
		$value = array_slice(func_get_args(),2);
		return $this->exec("DELETE FROM $table WHERE $condition", $value);
		
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
	 *
	 * @param string  $path  A valid path where the SQL statements reside.
	 *
	 * @return bool          Wether commit was succesful or not.
	 *
	 * @updated 2011/AUG/26 15:32 It now executes lines by line inside a transaction.
	 */
	public function import($path=false){
		if (!is_string($path) || !file_exists($path))
			error('Could not import, missing file.');
		# split statements and execute'em one by one.
		$sql = file_get_contents($path);
		$this->instance->beginTransaction();
		$i = 0;
		foreach (explode(';', $sql) as $statement) {
			if (!$statement = trim($statement)) continue;
			$i++;
			try { 
				$this->instance->exec($statement);
			} catch (PDOException $e) { 
				$this->instance->rollBack();
				$word = Utils::firstword($statement);
				$line = preg_match_all("/[\n\r]/", substr($sql, 0, strpos($sql, $statement)), $m);
				error("Imported failed at line $line on statement “{$word}…”."); 
			}
		}
		return $this->instance->commit();
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
	 * Check if Table exists
	 *
	 * @return bool wether table exists or not.
	 *
	 * @working 2011/AUG/25 18:49
	 * @created 2011/AUG/25 18:28
	 */
	public function is_table($table=false){
		if (!is_string($table)) error('Must Provide a table name');
		switch($this->driver){
			case 'mysql': $sql =
			  "SELECT count(*)
			     FROM information_schema.tables
			    WHERE `table_schema`='{$this->name}' AND `table_name`='{$table}'";
			break;
		}
		return (bool)$this->instance->query($sql)->fetchColumn();
	}

	/**
	 * Returns the Primary Key Column name.
	 * @author Hector Menendez <h@cun.mx>
	 * @licence http://etor.mx/licence.txt
	 * @created 2011/SEP/16 00:49
	 */
	private function primary_key($table=false){
	 	if (!is_string($table)) error('A table name is required.');
	 	$qry = $this->query("SHOW KEYS FROM $table WHERE Key_name='PRIMARY'");
	 	return $qry[0]['Column_name'];
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
	 * Makes sure a valid column declaration is being passed as an associative
	 * array of column names and their respective value. It can also be an array
	 * of arrays. example:
	 * 
	 *    method('table', array(
	 *      'col1' =>  666,
	 *      'col2' => 'sss'
     *    ));
	 * @updated 2011/SEP/16 02:49 Removed string support & improved array checking.
	 * @updated 2011/SEP/15 23:43 Added Array of arrays support.
	 * @updated 2011/AUG/24 19:27 Added description and example.
	 * @working 2011/AUG/24 17:44 Moved from $this->insert()
	 * @created 2011/AUG/24 16:38
	 */
	private function column_args($col=false){
		if (!is_array($col) ||  empty($col)) return false;
		$count  = count($col);
		$allarr = ($count == count(array_filter($col, function($a){return is_array($a);})));
		if (
			# column is filled with non_arrays, but... is it associative?
			(!$allarr && !Utils::is_assoc($col))
			# column is filled with arrays, it must not be associative.
		||	($allarr && Utils::is_assoc($col))
			# column is filled with arrays, each array must be associative 
		||	($allarr && $count != count(array_filter($col, function($a){return Utils::is_assoc($a);})))
		) return false;
		# be consistent.
		return ($allarr? $col : array($col));
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
	 * @working 2011/AUG/24 17:09 Moved from $this->select();
	 * @created 2011/AUG/24 17:00
	 */
	private function is_condition($condition){
		return
			stripos($condition, 'WHERE') === false && # doesn't have WHERE [auto added]
		 	preg_match('%=|<|>|!|~%', $condition);    # has relational operators
	}

	/**
	 * PDOException extractor.
	 */
	private function error(&$exception){
		$e = $exception->getMessage();
		switch($this->driver){
			case 'mysql' : $e = substr($e, strrpos($e, ']')+2); break;
			case 'sqlite': $e = substr($e,  strpos($e, ':')+1); break;
		}
		return error($e);
	}

}