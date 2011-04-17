<?php
/**
 *	Documentation Library
 *	Generates documentation from source code.
 *
 *	@note	this is an extremely simple class, in order to work	it needs the 
 *			code to follows strict rules.
 *
 *	@version	1.0R2 [02/APR/2011]
 *	@author		Hector Menendez	[h@cun.mx]
 *
 *	@log	esta es una prueba.
 *	@log 	esta es otra prueba
 *
 *	@todo	abstract methods are not supported yet. parser block detection.
 */
abstract class docs extends Library {

	private static $regex_identifiers = '/^[\w\t ]*(function|class)\s+/i';
	private static $regex_callers = '/[\w_]+(\:{2}){0,1}[\w_]+\((((?>[^()]*)|(?R))*)\)/imx';

	private static $control_cache;
	private static $control_view;
	private static $control_root;

	public static function _construct(){
		self::$control_cache = TMP.'all.'.__CLASS__;
		self::$control_view = LIBS.__CLASS__.'.view'.EXT;
		if (!file_exists(self::$control_view))
			error('Documentation View is missing.');
	}

######################################################################### PUBLIC

	/**
	 *	Documentation Controller
	 *	Parses all files available and shows a basic interface.
	 */
	public static function control($args=false){
		self::$control_root = array_shift($args);
		# if no argument found, load main view.
		if (!count($args) || empty($args[0])) return self::control_main();
		$action = 'control_'.strtolower(array_shift($args));
		if (!method_exists(__CLASS__,$action)) Core::route_404();
		call_user_func('self::'.$action,$args);
	}

	/**
	 *	All
	 *	Traverses all files in framework and retrieves all documentation.
	 */
	public static function parse_all($extra=false){
		$files = self::dir_read(substr(ROOT,0,-1));
		$array = array();
		foreach($files as $file){
			$file = self::parse($file);
			if (empty($file)) continue;
			# merge with resulting array.
			foreach($file as $k=>$v) $array[$k] = $v;
		}
		ksort($array);
		return $array;
	}

##################################################################### CONTROLLER

	private static function control_main(){
		$args = func_get_args();
		$db = self::_control_db_get();
		include self::$control_view;
	}

	private static function control_class($a){
		$db = self::_control_db_get();
		# only if object exists
		if (!isset($a[0]) || empty($a[0]) || !isset($db[$a[0]])) Core::route_404();
		# if method  sent, check for existance.
		$c = $db[$a[0]];		# class
		$m = isset($c['methods'])? $c['methods'] : array();		# methods
		if (isset($a[1]) && !empty($a[1]) && !isset($m[$a[1]])) Core::route_404();

		if (!isset($_POST['view']))
			error('This functionality is still missing','Warning!');

		if (!isset($a[1])) self::response_json($c);
		elseif(isset($a[1])) self::response_json($m[$a[1]]);

		error("This should not happen in ".__METHOD__);
		exit(2);
	}

	private static function response_json($array){
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 23 Jun 1981 03:00:00 GMT');
		header('Content-type: application/json');
		echo json_encode($array);
		exit(0);
	}

	private static function control_reload($args=false){
		if (file_exists(self::$control_cache))
			unlink(self::$control_cache);
		header("Refresh: 2; url=\"{$_SERVER['HTTP_REFERER']}\"");
		echo "<h1 style='color:#0C0'>Success</h1><h3>Generating Documentation.</h3>";
		exit(1);
	}

	private static function _control_db_get(){
		# get all documentation on the fly, serialize it, and cache it.
		if (!file_exists(self::$control_cache)){
			$db = self::parse_all();
			file_put_contents(self::$control_cache, serialize($db));
		}
		# file exists, get from cache.
		else
			$db = unserialize(file_get_contents(self::$control_cache));
		return $db;
	}

###################################################################### INTERNALS

	/**
	 * File Parser
	 * Extracts documentation from specified file.
	 *
	 * @version 1.0-R3 [2011|APR|11]
	 *
	 * @todo	for some reason methods are not being ordered.
	 *
	 * @log 1-0-R3	improved Method Parent Class finder.
	 * @log	1.0-R2 [2011|APR|08]
	 *
	 * @param	path[string]	Target file's full path.
	 * @return 
	 */
	public static function parse($path=false){
		if (!@file_exists($path)) error('Invalid Path.');
		$classes = array();
		$methods = array();
		$cont = file_get_contents($path);
		$line = file($path, true, FILE_SKIP_EMPTY_LINES);
		# tokenize string, so it's easier to identify its elements.
		$token = token_get_all($cont);
		foreach($token as $tk){
			# we're only interested in Documentation, thank you.
			if (!is_array($tk) || $tk[0] !== T_DOC_COMMENT) continue;
			$comment = preg_split('/(\n|\r)/',$tk[1]);
			# find the nearest declaration. The next line with words must be it.
			$rx = '/'
				 .	'(?:(?<permission>final|abstract)\s+){0,1}'
				 .	'(?:(?<visibility>public|private|protected)\s+){0,1}'
				 .	'(?:(?<static>static)\s){0,1}'
				 .	'(?<type>function|class)'
				 .	'(?:(?<=class)' # if class "behind"
				 .	  '\s+(?<cname>[a-z0-9_]+)(?:\s+extends\s+(?<parent>[a-z0-9_]+)){0,1}'
				 .	'|'
				 .	  '\s+[\&]{0,1}(?<fname>[a-z0-9_]+))'.
				 '/i';
			$i = (int)$tk[2] + count($comment)-1;	# line number after comment
			do  preg_match($rx, $line[$i], $declaration); # find non-empty line.
			while (preg_match('/^\s*$/',$line[$i++]));
			if (empty($declaration)) continue;
			# remove non-string keys in matches and unify names.
			$info = array();
			$raw = $declaration[0];
			foreach($declaration as $k=>$v) {
				if ($k=='cname' || $k=='fname') $info['name'] = $v;
				elseif ($k=='parent' || $k=='type') $info[$k] = $v;
				elseif (is_string($k)) continue;
				unset($declaration[$k]);
			}
			$info['declaration'] = $declaration;
			$info['declaration']['raw'] = $raw;
			# parse documentation and merge it with declaration
			if (!$comment = self::comment($comment)) continue;
			$comment = array_merge($info, $comment);
			unset($declaration, $raw, $info);
			# get the source code for each element by traversing the file.
			# only if there's curly brackets [abstract methods don't have'em]
			$curly_opened = 0;
			$curly_closed = 0;
			$source = '';
			$found = false;
			$limit = (int)count($line)-1;
			$rx = '/[\{\;]$/';
			$i--;
			do {
				if (!$found){
					while($i<=$limit && !preg_match($rx, $line[$i], $m)) $i++;
					if (!isset($m[0]) || $m[0] != '{') break;
					$found = true;
				}
				# if no curly braces found skip to next line
				$curly_opened += (int)substr_count($line[$i],'{');
				$curly_closed += (int)substr_count($line[$i],'}');
				# add source line
				$source .= $line[$i];	
			} while($i++ < $limit && $curly_opened != $curly_closed);
			# set the source, remove documentation.
			if (!empty($source)) 
				$source = preg_replace('%(/\*\*.*?\*/)%s','',$source);
			# attempt to remove uneeded identation based on the first line.
			if (preg_match('/^(\s+)/', $source, $tmp)){
				$tmp = $tmp[1];
				$source = preg_replace('%^'.preg_quote($tmp,'/').'%m','', $source);
			}
			$comment['source'] = $source;

			# separate methods so we can traverse them and find them a parent
			if 	   ($comment['type']== 'function') $methods[] = $comment;
			elseif ($comment['type']== 'class'   ) $classes[] = $comment;
			unset($comment);
		}
		$result = array();
		# find the parent class name by traversing each line in current classes
		# is not efficient, but I cannot think of a better way of doing it now.
		# once found, generate a resulting array, sorting it by hierarchy.
		foreach($classes as $ckey => $class){
			$cname = $class['name'];
			if (empty($class['source'])) continue;
			$source = explode("\n", $class['source']);
			foreach($methods as $mkey => $method){
				$found = false;
				$mname = $method['name'];
				$rx = '%^\s*(?:[^\/\#\*])*function\s*\&?('.addslashes($mname).')%i';
				foreach ($source as $l){
					# regex is slow, so use stripos for basic matching.
					if (
						false === stripos($l, 'function') ||
						false === stripos($l, $mname) || 
						!preg_match($rx, $l)) # detect comments
						continue;
					$found = true;
					break;
				}
				if (!$found) continue;
				# match found, append method to class
				$method['parent'] = $cname;
				if (!isset($class['methods'])) $class['methods'] = array();
				$class['methods'][$method['name']] = $method;
			}
			if (isset($class['methods'])) ksort($class['methods']);
			$result[$class['name']] = $class;
		}
		ksort($result);
		unset($source, $methods, $classes, $method, $class, $i);
		return $result;
	}

	/**
	 * Comment Parser
	 * Extracts documentation from specified comment
	 *
	 * @version 1.0-R4	[2011|APR|09]
	 * @log 	Modified the array arrangement [2011|APR|09]
	 * @param	(string)comment	Valid comment to be parsed.
	 */
	 public static function comment($comment=false){
		$cm = is_array($comment)? implode("\n",$comment) : $comment;
		# make sure this is a comment
		if (!preg_match('%/[*]{2}(.*?)\*/%s', $cm, $match)) return false;
		$rx = '%(?:(?<=[^@])[*][ \t]+'
			. '(?<desc>[^@\r\n]+)|(?:[*][ \t]+[@]'
			. '(?<args>[^*]+(?:\*\s*\w+[^\r\n]*)*)))%mi';
		# extract description and parameters [removing white spaces.]
		if (!preg_match_all($rx, $match[1], $match)) return false;
		$match = array(
			'description' => array_filter($match['desc']), 
			'arguments' => array_filter($match['args']));
		foreach ($match as $k => $v){
			$v = preg_replace(array('/\s+/','/\*+/','/[  ]+/'),' ', $v);
			if ($k === 'description') continue;
			# transform arguments to key=>val
			foreach($v as $i => $x){
				$pos = strpos($x, ' ');
				$val = trim(substr($x, $pos));
				$v[substr($x, 0, $pos)][] = empty($val)? true : $val;
				unset($v[$i]);
			}
			$match[$k] = $v;
		}
		return array(
			'title' => array_shift($match['description']),
			'description' => implode(' ', $match['description']),
			'arguments'=> array_map(function($v){
				if (is_array($v) && count($v)==1) return $v[0];
				return $v;
			}, $match['arguments'])
		);
	}

	private static function comment_merge($array){
		$c = array('class'=>array(), 'method'=>array() );
		foreach($array as $a)
			foreach(array_keys($c) as $k)
				if (!empty($a[$k]))	$c[$k] = array_merge($c[$k], $a[$k]);
		return $c;
	}


	private static function comment_calls_get($lines){
		$found = array();
		foreach($lines as $i=>$line){
			# ignore lins with function or class declarations
			if (preg_match(self::$regex_identifiers, $line)) continue;
			$blk = array();
			self::comment_calls_match($line,$blk);
			if (empty($blk)) continue;
			foreach ($blk as $blk) $found[] = $blk;
		}
		return array(array_values(array_unique($found)), $lines);
	}

	private static function comment_calls_match($line, &$block){
		if (!preg_match(self::$regex_callers, $line, $match)) return false;
		# if recursive matches found, make a recursive call.
		if(!empty($match[1])) self::comment_calls_match($match[1], $block);
		# determine calling function name
		$line = preg_replace('/\s+/', ' ',$match[0]);
		$line = trim(substr($line,0,strpos($line,'(')));
		if (!$pos = strrpos($line,' ')) $pos = 0;
		array_push($block, substr($line,$pos>1? $pos-1 : 0));
	}


###################################################################### UTILITIES

	private static function dir_read($path){
		$root = opendir($path);
		$list = array();
		while ( ($file = readdir($root)) !== false){
			if (strcmp($file, '.') === 0 || strcmp($file, '..') === 0) continue;
			$path_file = $path.SLASH.$file;
			$isdir = is_dir($path_file);
			if (substr($file,strlen(EXT)*-1) !== EXT && !$isdir) continue;
			if ($isdir) $list = array_merge($list, self::dir_read($path_file));
			else array_push($list, $path_file);
		}
		closedir($root);
		return $list;
	}

	private static function &array_swap(&$array=false){
		$m = '['.__METHOD__.']';
		if (!is_array($array)) error("An array is required. $m");
		$names = func_get_args();
		array_shift($names);
		if (empty($names)) error("Replacement keys are needed. $m");
		if (count($names) !== ($num=count($array)) ){
			print_r($array);
			print_r($names);
			error("$num Replacements are needed. $m");
		}
		$i = 0;
		foreach($array as $key=>$val){
			$tmp = $val;
			$name = $names[$i++];
			unset($array[$key]);
			if (is_int($name) || $name) $array[$name] = $tmp;
			if ($i>=$num) break;
		}
		return $array;
	}

}