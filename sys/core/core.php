<?php
/**
 * Framework Core
 * The mothership, The Red Leader, The master of disaster.
 *
 * @version	v2.1 Palmera Branch	[2011|AUG|21]
 * @author	Héctor Menéndez     [h@cun.mx]
 *
 * @log [2011|MAR|28]	- Forked from GIRO 
 * @log [2011|APR|01] 	- Implemented Basic URI & Routing handling 
 * @log [2011|APR|14]	- Changed application for application to avoid conflicts 
 *						  between the newly created method in Librarytodo
 * @log [2011|AUG|21]	- Moved header and file from Library, since thos weren't 
 *                        really necesary all over the framewrork.
 *                      - Added basic database support via sqlite and the newly
 *                        added-to-core Database library.
 *
 * @todo	Make sure the uri always ends with a slash.
 * @todo	find a way that every todo appears automagically in a file.
 * @todo	SESSION HANDLING TO REPLACE APP TEMP FILES.
 * @todo	cache static files by sending 404 if their counter part exists in tmp
 * @root
 */
abstract class Core extends Library {

	private static $library     = array('core');
	private static $config      = array();
	private static $application = array();
	private static $queue       = array();
	private static $file        = array();
	private static $DB          = null;

	public static function _construct(){
		self::$config = self::config_get();
		spl_autoload_register('self::library');
		self::uri_parse();
		Application::load();
	}

######################################################################### PUBLIC

	/**
	 * Framework TMP data management with simple sqlite DB [beta]
	 */
	public static function &db(){
		if (!self::$DB){
			if (!file_exists(CORE.'db.sql'))
				error('Core Database template is missing.');
			self::$DB = DB::sqlite(DB);
			self::$DB->import(CORE.'db.sql');
			return self::$DB;
		}
		return self::$DB;
	}

	/**
	 * Configuration Getter
	 * Retrieves the main configuration array.]
	 *
	 * @return [reference] [array] General configuration array.
	 */
	public static function &config_get($class = false){
		if (empty(self::$config)) self::$config = include(CORE.'config'.EXT);
		if (is_string($class)){
			$false = false;
			if (!isset(self::$config[$class])) return $false;
			return self::$config[$class];
		}
		return self::$config;
	}

	/**
	 * Library AutoLoader
	 * Autodetermines the location of classes and loads them as they are 
	 * required. It also runs static pseudo-constructors, if available.
	 * It used without arguments, returns an array of loaded classes.
	 *
	 * @log 2011/AUG/29 21:01 - Naming convention change: class containing
	 *                          underscores in the name, will be translated to 
	 *                          dots for file discovery.
	 *                        - Added support for sub-libraries.
	 *
	 * @log 2011/AUG/25 17:06   Libraries can now be stored in it's own directory.
	 *                          it also creates a constant with the uppercased name
	 *                          holding appliucation path.
	 *
	 * @return array            Loaded Library array or sends error.
	 */
	public static function library($name=false){
		$name = strtolower($name);
		if (!is_string($name)) return self::$library;
		if (in_array($name, self::$library)) return true;
		# for files, all underscores, will be treated as dots.
		$name = str_replace('_', '.', $name);
		$found = file_exists($path=CORE.$name.EXT) ||
				      ( # One-File-Library
				 file_exists($path=LIBS.$name.EXT) &&
				 define(strtoupper($name), pathinfo($path, PATHINFO_DIRNAME).SLASH, true)
				 ) || ( # Multi-File-Library
				 file_exists($path=LIBS.$name.SLASH.$name.EXT) &&
				 define(strtoupper($name), pathinfo($path, PATHINFO_DIRNAME).SLASH, true)
				 ) || ( # Sub-Library
				 	($sub = substr($name, 0, strpos($name, '.'))) &&
				 	file_exists($path=LIBS.$sub.SLASH.$name.EXT)  &&
				 	define(
					 	strtoupper(str_replace('.', '_', $name)),
					 	pathinfo($path, PATHINFO_DIRNAME).SLASH, true)
				 );
		# restore original naming.
		$name = str_replace('.', '_', $name);
		if (!$found) {
			$rx = '/\w+(control|model|view)/';
			# If an APP is active, check its folder.
			if (!defined('APP_NAME') || !preg_match($rx, $name, $match))
				error("Library $name does not exist.");
			$replace = ".{$match[1]}".EXT;
			if ($match[1] == 'control') $replace = EXT;
			$path = APP.str_ireplace($match[1], $replace, $name);
			if (!file_exists($path)) error("Application $name is undefined.");
			include $path;
			array_push(self::$library, $name);
			return true;
		}
		include $path;
		if (method_exists($name,'_construct'))
			call_user_func("$name::_construct");
		array_push(self::$library, $name);
		return true;
	}

	/**
	 * Autoshutdown Controller
	 * Checks if loaded libraries have pseudo-destructors available and, runs 
	 * them before the framework shuts down.
	 */
	public static function shutdown(){
		foreach (array_reverse(self::$library) as $l){
			if (method_exists($l,'_destruct')) call_user_func("$l::_destruct");
		}
		if (!Core::config('debug')) return;
		file_put_contents(TMP.'benchmark',
			'Microtime: '.(string)(microtime(true) - BMK)."\n".
			'Peak Mem : '.(string)(memory_get_peak_usage(true)/1024)." Kb\n"
		);
	}	

	private static $language = null;

	/**
	 * Language Management
	 * Set, get and Search the language configuration [by language key]
	 * ie: es-mx or es
	 */
	public static function language($get=false, $set=false){
		$lang = self::config('language');
		# if no current language is defined, do it now.
		if (!self::$language){
			self::$language = array_keys($lang);
			self::$language = array_shift(self::$language);
		}
		# if nothing specified return the current language
		if (!$get && !$set) {
			$lang[self::$language]['key'] = self::$language;
			return $lang[self::$language];
		}
		# set value
		if ($set){
			if (
				!is_string($get) || strlen($get)!=5         || # key must be xx-xx
				!is_array($set)                             ||
				count($set) != 3                            ||
				array_keys($set) !== range(0,2)             || # array is not associative
				!($set = explode('|', implode('|',$set)))   || # convert all values to string
				strlen($set[0])!=2
			) error('Invalid language format');
			$conf = &self::config_get();
			self::$language = $get;
			$conf['core']['language'][$get] = $set;
			$set['key'] = $get;
			return $set;
		}
		$get = (string)$get;
		$len = strlen($get);
		# first check for the natural key-name
		if ($len == 5 && isset($lang[$get])) {
			self::$language = $get;
			$lang[$get]['key'] = $get;
			return $lang[$get];
		}
		# next, check for the second common key, return first match
		elseif ($len == 2) foreach($lang as $key=>$lan) if ($lan[0] == $get) {
			self::$language = $lan['key'] = $key;
			return $lan;
		}
		# bye bye
		return false;
	}

	/**
	 * Removes all headers sent up to this point.
	 */
	public static function headers_remove(){
		foreach(headers_list() as $h)
			header_remove(substr($h, 0, strpos($h, ':')));
	}

	/**
	 * Header Shorthand
	 */
	public static function header($code = null){
		if (headers_sent()) return false;
		if (is_int($code)){
			switch((int)$code):
			case 200: $head = 'HTTP/1.1 200 OK';							break;
			case 304: $head = 'HTTP/1.0 304 Not Modified';					break;
			case 400: $head = 'HTTP/1.0 400 Bad request'; 					break;
			case 401: $head = 'HTTP/1.0 401 Authorization required';		break;
			case 402: $head = 'HTTP/1.0 402 Payment required'; 				break;
			case 403: $head = 'HTTP/1.0 403 Forbidden'; 					break;
			case 404: $head = 'HTTP/1.0 404 Not found';						break;
			case 405: $head = 'HTTP/1.0 405 Method not allowed';			break;
			case 406: $head = 'HTTP/1.0 406 Not acceptable';				break;
			case 407: $head = 'HTTP/1.0 407 Proxy authentication required'; break;
			case 408: $head = 'HTTP/1.0 408 Request timeout';				break;
			case 409: $head = 'HTTP/1.0 409 Conflict';						break;
			case 410: $head = 'HTTP/1.0 410 Gone';							break;
			case 411: $head = 'HTTP/1.0 411 Length required';				break;
			case 412: $head = 'HTTP/1.0 412 Precondition failed';			break;
			case 413: $head = 'HTTP/1.0 413 Request entity too large';		break;
			case 414: $head = 'HTTP/1.0 414 Request URI too large';			break;
			case 415: $head = 'HTTP/1.0 415 Unsupported media type';		break;
			case 416: $head = 'HTTP/1.0 416 Request range not satisfiable'; break;
			case 417: $head = 'HTTP/1.0 417 Expectation failed';			break;
			case 422: $head = 'HTTP/1.0 422 Unprocessable entity';			break;
			case 423: $head = 'HTTP/1.0 423 Locked';						break;
			case 424: $head = 'HTTP/1.0 424 Failed dependency';				break;
			case 500: $head = 'HTTP/1.0 500 Internal server error';			break;
			case 501: $head = 'HTTP/1.0 501 Not Implemented';				break;
			case 502: $head = 'HTTP/1.0 502 Bad gateway';					break;
			case 503: $head = 'HTTP/1.0 503 Service unavailable';			break;
			case 504: $head = 'HTTP/1.0 504 Gateway timeout';				break;
			case 505: $head = 'HTTP/1.0 505 HTTP version not supported';	break;
			case 506: $head = 'HTTP/1.0 506 Variant also negotiates';		break;
			case 507: $head = 'HTTP/1.0 507 Insufficient storage';			break;
			case 510: $head = 'HTTP/1.0 510 Not extended';					break;
			default: return false;
			endswitch;
		}
		elseif(is_string($code)) return false; # add more header shortcuts here
		else return false;
		header($head);
		return true;
	}
	
	/**
	 * Show Error with Style
	 * Do I really need to say more, b?
	 */
	public static function error_show($type, $message, $file, $line, $trace){
		if (!parent::config('error')) exit(2);
		$debug = parent::config('debug');
		switch($type){
			case E_ERROR:			$txt = "Engine Error";	break;
			case E_PARSE:			$txt = "Parse Error";	break;
			case E_CORE_ERROR:		$txt = "Core Error";	break;
			case E_COMPILE_ERROR:	$txt = "Compile Error"; break;
			case E_USER_ERROR: 		$txt = "Error"; 		break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$txt = "Warning";
				break;
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_STRICT:
				$txt = "Notice";
				break;
			default: # unknown error type? get error constant name.
				$tmp = get_defined_constants(true);
				$txt = array_search($type, $tmp['Core'], true);
		}
		$prop = array_shift($trace);
		$class = isset($prop['class']) && $debug? $prop['class'] : '';
		echo "<style>",
			 "h1 { color:#333;  } ",
			 "h1 span.Warning { color:#F60; } ",
			 "h1 span.Error   { color:#F00; } ",
			 "h1 span.Notice  { color:#06F; } ",
			 "td { color:#444; }",
			 "td.file { color:#300; text-align:right; font-size:.7em; padding-right:1em; line-height:1em; }",
			 "</style>";

		echo "\n<h1>$class <span class='$txt'>$txt</span></h1><h2>$message</h2>\n";
		if (!$debug || empty($trace)) return self::error_exit($txt);
		$tt = array('file'=>$file, 'line'=>$line);
		array_unshift($trace, $tt);
		echo "\n<pre><table>\n";
		#print_r($trace); die;
		foreach($trace as $t){
			if (!isset($t['file']) || !isset($t['line'])) continue;
			$line = self::file($t['file']);
			$lnum = $t['line']-1;
			$line = trim($line[$lnum]);
			if (!preg_match('/\w+/', $line)) continue;
			$file = substr($t['file'], stripos($t['file'],PATH) + strlen(PATH));
			echo "\t<tr><td class='file''>$file:$lnum</td><td>$line</td></tr>\n";
		};
		echo "</table></pre>\n";
		return self::error_exit($txt);
	}

	private static function error_exit($type){
		if (stripos($type, 'notice') === false ) exit(2);
		return true;
	}


	/**
	 * Simple File Cacher
	 * Stores files in a static var so they can be constantly accessed.
	 *
	 * @param [string $path	  The file path, it will be used to identify the file.
	 * @param [bool]  $array  Array or plain file?
	 * @param [int]   $flags  FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES
	 *
	 * @return	[mixed]	
	 */
	public  static function file($path=null, $array=true, $flags=0){
		if ($path === null) return self::$file;
		if (!file_exists($path)) return false;
		$key = $path;
		$mode = $array? 'array' : 'string';
		if (isset(self::$file[$key][$mode])) return self::$file[$key][$mode];
		if (!isset(self::$file[$key])) self::$file[$key] = array();
		self::$file[$key][$mode] = $array?
			file($path, $flags) : 
			file_get_contents($path);
		return self::$file[$key][$mode];
	}

	/**
	 * Mime type detector
	 * Too simple,just extracts the file extension and retrieves its mime type
	 * according to config file.
	 */
	public static function file_type($path){
		$type = self::config('mime-types');
		# extract extension.
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		if (empty($ext) || !in_array($ext, array_keys($type)))
			return 'text/plain';
		return $type[$ext];
	}

	/**
	 * Add Method to Queue
	 */
	public static function queue($fn=false){
		$method = func_get_args();
		if (empty($method)) return false;
		array_push(self::$queue, $method);
		return true;
	}

	/**
	 * Process queue
	 */
	public static function queue_run(){
		if (!is_array(self::$queue)) return false;
		foreach(array_reverse(self::$queue) as $m){
			$method = array_shift($m);
			if (is_callable($method)) call_user_func_array($method, $m);
		}
	}

	/**
	 * No Comments
	 * Strip comments from given source code.
	 *
	 * @note an T_OPEN_TAG is added by default so the tokenizer works.
	 */
	public static function nocomments($str, $addopentag=true){
		$comment = array(T_COMMENT, T_DOC_COMMENT);
		$foundopentag = false;
		if ($addopentag) $str = '<'.'?'.$str;
		$tokens = token_get_all($str);
		$source = '';
		foreach($tokens as $token){
			if (is_array($token)){
				# if we added an open tag, ignore it.
				if ($addopentag && !$foundopentag && $token[0] === T_OPEN_TAG){
					$foundopentag = true;
					continue;
				}
				if (in_array($token[0], $comment)) continue;
				$token = $token[1];
			}
			$source .= $token;
		}
		return $source;
	}

	/**
	 * URI Parser
	 * Extracts information from the URI and explodes it to pieces so it can be
	 * better understood by the framework.
	 */
	private static function uri_parse($key='REQUEST_URI'){
		if (!isset($_SERVER[$key]) || $_SERVER[$key]=='')
			error('The URI is unavailable [crap].');
		# permanently redirect [non-root] requests  containing a trailing slash
		$tmp = str_replace(PATH, '', $_SERVER[$key]);
		if (!empty($tmp) &&	substr($_SERVER[$key], -1) == '/') {
			header ('HTTP/1.1 301 Moved Permanently');
			header ('Location: '.substr($_SERVER[$key], 0, -1));
			stop();
		}
		# catch calls to pub dir, and parse them differently.
		define('URI', str_replace(BASE,'',$_SERVER[$key]));
	}

}