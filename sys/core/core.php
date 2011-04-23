<?php
/**
 * Framework Core
 * The mothership, The Red Leader, The master of disaster.
 *
 * @version	v2.0R3	[2011|MAR|28]	Based upon previous and undocumented Framework.
 * @author	Héctor Menéndez [h@cun.mx]
 *
 * @log [2011|MAR|28]	Forked from GIRO 
 * @log [2011|APR|01] 	Implemented Basic URI & Routing handling 
 * @log [2011|APR|14]	Changed application for application to avoid conflicts 
 *						between the newly created method in Librarytodo
 *
 * @todo	Make sure the uri always ends with a slash.
 * @todo	find a way that every todo appears automagically in a file.
 * @todo	SESSION HANDLING TO REPLACE APP TEMP FILES.
 * @todo	cache static files by sending 404 if their counter part exists in tmp
 * @root
 */
abstract class Core extends Library {

	private static $config = array();
	private static $library = array('core');
	private static $application = array();

	public static function _construct(){
		self::$config = self::config_get();
		spl_autoload_register('self::library');
		self::uri_parse();
		if (strpos(URI, PUB_URL)!==false) Application::external();
		else Application::load();
	}

######################################################################### PUBLIC

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
	 * @return [array] Loaded Library array or sends error.
	 */
	public static function library($name=false){
		$name = strtolower($name);
		if (!is_string($name)) return self::$library;
		if (in_array($name, self::$library)) return true;
		$found = file_exists($path=CORE.$name.EXT) ||
				 file_exists($path=LIBS.$name.EXT);
		if (!$found) error("Library <u>$name</u> does not exist");
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
	}

	/**
	 * URI Parser
	 * Extracts information from the URI and explodes it to pieces so it can be
	 * better understood by the framework.
	 */
	private static function uri_parse($key='REQUEST_URI'){
		if (!isset($_SERVER[$key]) || $_SERVER[$key]=='')
			error('The URI is unavailable [crap].');
		# catch calls to pub dir, and parse them differently.
		define('URI', str_replace(BASE,'',$_SERVER[$key]));
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
			$line = parent::file($t['file']);
			$lnum = $t['line']-1;
			$line = trim($line[$lnum]);
			if (!preg_match('/\w+/', $line)) continue;
			$file = substr($t['file'], stripos($t['file'],PATH) + strlen(PATH));
			echo "\t<tr><td class='file''>$file:$lnum</td><td>$line</td></tr>\n";
		};
		echo "</table></pre>\n";
		return self::error_exit($txt);
	}

	public static function error_exit($type){
		if (stripos($type, 'notice') === false ) exit(2);
		return true;
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


}