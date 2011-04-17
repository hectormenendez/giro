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
 * @log [2011|APR|14]	Changed application for app_load to avoid conflicts 
 *						between the newly created method in Librarytodo
 *
 * @.	Make sure the uri always ends with a slash.
 * @todo	find a way that very todo appears automagically in a file.
 *
 * @root
 */
abstract class Core extends Library {

	private static $config = array();
	private static $library = array('core');
	private static $application = array();
	private static $route_index = null;

	public static function _construct(){
		self::$config = self::config_get();
		if (!self::$route_index = parent::config('route_index'))
			error('Undefined Default Application');
		spl_autoload_register('self::library');
		$uri = self::route_uri_parse();
		self::app_load($uri);
	}

######################################################################### PUBLIC

	/**
	 * Autoshutdown Controller
	 * Checks if loaded libraries have pseudo-destructors available and, runs 
	 * them before the framework shuts down.
	 */
	public static function shutdown(){
		foreach (array_reverse(self::$library) as $l)
			if (method_exists($l,'_destruct')) call_user_func("$l::_destruct");
	}

	/**
	 * Library AutoLoader
	 * Autodetermines the location of classes and loads them as they are 
	 * required. It also runs static pseudo-constructors, if available.
	 * It used without arguments, returns an array of loaded classes.
	 *
	 * @return [array] Loaded Library array or sends error.
	 */
	public static function &library($name=false){
		$si = true;
		$no = false;
		$name = strtolower($name);
		if (!is_string($name)) return self::$library;
		if (in_array($name, self::$library)) return $si;
		$found = file_exists($path=CORE.$name.EXT) ||
				 file_exists($path=LIBS.$name.EXT);
		if (!$found) error("Library <u>$name</u> does not exist");
		include $path;
		if (method_exists($name,'_construct'))
			call_user_func("$name::_construct");
		return $si;
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
	 * 404 Router
	 * Sends a 404 header to the browser.
	 *
	 * @todo This is currently sending 500, since error modifies the heeader.
	 * @todo Move this method to the library and update all the references to it.
	 */
	public static function route_404($ctrl=false, $iserr=false){
		header('HTTP/1.0 404 Not Found');
		if ($ctrl == self::$route_index)
			error('Default Application Missing');
		# if there's an error controller load it. [avoiding recursion]
		if ($iserr === true || !$error = parent::config('route_error'))
			error('File Not Found','404');
		self::app_load(array('ctrl'=>$error, array($ctrl)), true);
	}

	/**
	 * URI Parser
	 * Extracts information from the URI and explodes it to pieces so it can be
	 * better understood by the framework.
	 */
	private static function route_uri_parse($key='REQUEST_URI'){
		if (!isset($_SERVER[$key]) || $_SERVER[$key]=='')
			error('The URI is unavailable [crap].');
		# get rid of the path and file name [if any]
		$uri = str_replace(PATH,'/',str_replace(BASE,'',$_SERVER[$key]));
		# sanitize a little bit, by removing double slashes
		while (strpos($uri,'//')!==false) $uri = str_replace('//','/',$uri);
		# determine the application to run
		return self::route_uri_identify($uri);
	}

	private static function route_uri_identify($uri){
		# uri starts with '?' then treat it as a GET request
		if (isset($uri[0]) && $uri[0] == '?'){
			$uri = self::route_uri_filter(substr($uri,1),'\&\=');
			foreach(explode('&',$uri) as $v){
				$v = explode('=',$v);
				if (!isset($v[1])) $v[1] = null;
				$var[$v[0]] = $v[1];
			}
			$uri = array('ctrl'=>'__index__','args'=>$var);
		}
		# uri contains slashes
		elseif ($uri!='/' && strpos($uri,'/')!==false){
			$uri = self::route_uri_filter($uri,"\/");
			$uri = explode("/", $uri);
			array_shift($uri);
			$uri = array('ctrl'=>array_shift($uri), 'args'=>$uri);
		}
		# uri is empty
		else $uri = array('ctrl'=>'__index__', 'args'=>array());
		return $uri;
	}

	private static function route_uri_filter($uri,$xtra=''){
		$char = parent::config('uri_chars');
		return preg_replace('/[^'.$xtra.$char.']/','',$uri);
	}

	/**
	 * Application Loader
	 * Checks if an application and its dependant model exists for a given URI,
	 * if so, loads the Controller & Model Classes so they handle the hussle.
	 *
	 * @param [array] $uri Internal array containing the ruting info.
	 *
	 * @todo	Reread the method, so the documentation reflects better what
	 *			this class does.
	 *
	 * @return [mixed][reference] Application controlller.
	 */
	private static function &app_load($uri, $iserr=false){
		# Trigger autoload for Application
		if (!class_exists('application')) error('Application Library Missing');
		foreach ($uri as $k=>$v) $$k = $v; # $ctrl, $args
		if ($ctrl == '__index__') $ctrl = self::$route_index;
		# Load the controller. [files have priority over directories]
		# ie: APP/main.php  overrides APP/main/main.php
		$found = file_exists($path_ctrl=APP.$ctrl.EXT) ||
				 file_exists($path_ctrl=APP.$ctrl.SLASH.$ctrl.EXT);
		if (!$found) self::route_404($ctrl, $iserr);
		# controller exists. is it loaded? no need to reload then.
		if (in_array($ctrl, self::$application))
			return self::$application[$ctrl];
		# define constants holding app's path and name
		$path_ctrl_dir = pathinfo($path_ctrl, PATHINFO_DIRNAME).SLASH;
		define('APP_PATH', $path_ctrl_dir);
		define('APP_NAME', $ctrl);
		# If a model exist, load it first.
		$model = false;
		$found = file_exists($path_model=APP.$ctrl.'.model'.EXT) ||
				 file_exists($path_model=APP.$ctrl.SLASH.'model'.EXT);
		if ($found)
			$model = self::app_inc($ctrl.'Model', $path_model, $args);
		# instantiate controller and push the model as [last] argument
		return self::app_inc($ctrl.'Control', $path_ctrl, $args, $model);
	}

	private static function &app_inc($name, $path, $args=array(), &$model=null){
		include $path;
		if (!class_exists($name,false)) error("Erroneus Declaration in $name");
		# if this is a controller and there's a model available, instantiate it.
		$instance = new $name;
		$instance->view = new View;
		$modelname = APP_NAME.'Model';
		if (is_object($model) && $model instanceof $modelname)
			$instance->model = &$model;
		# find a user-sent constructor ans instantiate it.
		if (method_exists($instance, APP_NAME))
			call_user_func_array(array($instance, APP_NAME), $args);
		return $instance;
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
			$line = file($t['file']);
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
}