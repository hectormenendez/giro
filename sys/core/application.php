<?php
/**
 * Application Common
 * Th construction of this classes is done manually in Core::app_load.
 */
class ApplicationCommon {
	/**
	 * Redirect all unknown method calls to static Library.
	 */
	public function __call($name, $args){
		if (method_exists('Library', $name))
			return call_user_func_array("Library::$name", $args);
		error("Undefined method $name");
	}
}

class Application extends Library {

	private static $default = null;
	private static $application = null;
	private static $queue = array();

	/**
	 * Destroys application temp data after a timeout.
	 */
	public static function _destruct(){
		if (!defined('APP_NAME')) return false;
		if (!$time = self::config('clean_timeout')) $time = 10;
		shell_exec("nohup php -r \"sleep(".$time."); @unlink('".TMP.UUID.'.'.APP_NAME."');\" > /dev/null & echo $!");
	}

	/**
	 * Application Loader
	 * Checks if an application and its dependant model exists for a given URI,
	 * if so, loads the Controller & Model Classes so they handle the hussle.
	 *
	 * @param [array] $uri Internal array containing the ruting info.
	 *
	 * @return [mixed][reference] Application controlller.
	 */
	public static function load($external=null, $args=null){
		if (!$external){
			if (!defined('URI')) error('The URI has not been parsed yet.');
			if (!self::$default = parent::config('default'))
				error('Default APP set incorrectly');
			$uri = self::identify();
			$ctrl = $uri['ctrl'] == '__index__'? self::$default : $uri['ctrl'];
			$args = $uri['args'];
			unset($uri);
		} else 
			$ctrl = $external;
		if (!$path = self::path_find('', $ctrl)) {
			if ($ctrl == self::$default)
				parent::error_500('Default Application Missing');
			parent::error_404(ucfirst($ctrl)." does not exist.");
		}
		# controller exists, define constants
		define('APP_PATH', pathinfo($path, PATHINFO_DIRNAME).SLASH);
		define('APP_NAME', $ctrl);
		define('APP_URL', URL.APP_NAME.SLASH);
		# construct application
		$null = null;
		$name = $external? APP_NAME.'_external' : APP_NAME;
		self::$application = self::construct($args,
							 self::construct('model', $null, $null, $name),
							 self::construct('view',  $null, $null, $name),
							 $name);
		if (!$external) self::unload();
	}

	/**
	 * Application Destructor
	 * This will run as soon as the controller ends its execution.
	 */
	private static function unload(){
		self::render();
		self::queue_run();
	}

	/**
	 * Application Constructor
	 * Instantiates the application and sets it up.
	 */
	private static function &construct($args=null, &$model=null, &$view=null, $construct=null){
		$false = false; 
		# if an array is sent as first parameter assume controller.
		# the path checking for it is done in the loader.
		$type = is_array($args)? 'control' : $args;
		if ($type != 'control' && !$path = self::path_find($type)) return $false;
		elseif ($type == 'control') $path = self::path_find();
		include $path;
		# Validate Declaration
		$inst = APP_NAME.ucfirst($type);
		if (!class_exists($inst, false)) error('Invalid '.ucwords(str_replace(APP_NAME, '', $inst)).' Declaration.');
		$inst = new $inst($args);
		# Views don't need constructors.
		if ($type == 'view') return $inst;
		# fill out controller.
		if ($type == 'control'){
			$inst->view  = &$view;
			$inst->model = &$model;
		}
		# run pseudo constructor
		if (method_exists($inst, $construct))
			call_user_func_array(array($inst, $construct), (array)$args);
		return $inst;
	}

	/**
	 * External file identifier
	 * Catches request to pub folder, check if the user is tryng to load a 
	 * dynamic file from the framework.
	 *
	 * @todo cache management
	 */
	public static function external(){
		$file = str_replace(PUB_URL, PUB, URI);
		# if the requested file exists on the server, serve it, but only if it's
		# not a text/plain. [unknown]
		if (file_exists($file)){
			$mime = Core::file_type($file);
			$time = gmdate('D, d M Y H:i:s', filemtime($file));
			#$cache = str_replace(PUB, TMP, $file).'.cache';
			# look for a cached version of the file.
			if ($mime == 'text/plain')	parent::warning_403('403 forbidden');
			$file = file_get_contents($file);
 			header("Last-Modified: $time GMT", true);
			header("Content-Type: $mime");
			echo $file;
			stop();
		}
		# the user is requesting an unexistent file, check if the server refers
		# to an application dynamic css / js.
		$msg = 'File Not Found.';
		$uri = str_replace(PUB_URL, '', URI);
		if (!$ext = pathinfo($uri, PATHINFO_EXTENSION)) parent::error_404($msg);
		$var = explode('.', substr($uri, 0, strpos($uri,$ext)-1));
		$dir = explode('/', array_shift($var));
		$app = array_shift($dir);
		$path = (empty($dir)? '' :'.'.implode('.',$dir)).'.'.$ext;
		if (!self::path_find('',$app) || !$path = self::path_find($path,$app))
			parent::error_404($msg);
		$mime = Core::file_type($file);
		if ($mime == 'plain/text') error_500('Internal Server Error');
		self::load($app, $var);
		#header("Last-Modified: ".gmdate('D, d M Y H:i:s',filemtime($path))." GMT", true);
		header("Content-Type: $mime");
		self::render($path, $var);
		stop();
	}



	/**
	 * Render View
	 * Creates an encapsulated scope so the view and extenal files can share it.
	 */
	private static function render($_PATH = null, $_VAR = null){
		if (!$_PATH) $__isview = true;
		if (!$_PATH && !$_PATH = self::path_find('.html')) return false;
		if (self::$application->view){
		 	# obtain all methods declared on the view set them on the global scope.
		 	foreach (self::helpers(self::$application->view) as $__k => $__v){
		 		if (function_exists($__k)) error("Can't use '$__k' as view-function.");
		 		eval($__v);
		 	}
	 	}
	 	if (isset($__isview)){
		 	# now, we make available all the variabes in the global scope for the 
		 	# view, and for the included css and js, by using a temp php that we'll
		 	# include here and there.
		 	$__isview = addslashes(serialize(View::$__vars));
		 	$__s = ""
		 	. '<'.'?'.'php\n"
		 	. "\$__s = unserialize(stripslashes(\"$__isview\"));\n"
		 	. "foreach ( \$__s as \$__k => \$__v) \$__s .= \$\$__k = \$__v;\n"
		 	. "unset(\$__s,\$__k,\$__v);\n";
		 	file_put_contents(TMP.UUID.'.'.APP_NAME, $__s);
	 	} else {
	 		foreach (View::$__vars as $__k => $__v) $$__k = $__v;
	 		unset($__s,$__k,$__v);
	 	}
	 	# if there's a scope available load it.
	 	if (file_exists(TMP.UUID.'.'.APP_NAME)) include TMP.UUID.'.'.APP_NAME;
		parent::header(200);
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	 	include $_PATH;
	}

	/**
	 * Add Method to Queue
	 */
	public static function queue(){
		$method = func_get_args();
		if (!is_array(self::$queue)) self::$queue = array();
		if (empty($method)) return false;
		array_push(self::$queue, $method);
		return true;
	}

	private static function queue_run(){
		if (!is_array(self::$queue)) return false;
		foreach(array_reverse(self::$queue) as $m){
			$method = array_shift($m);
			if (is_callable($method)) call_user_func_array($method, $m);
		}
	}

	/**
	 * Application Path Finder
	 * Attempt to load the controller.[files have priority over directories]
	 * ie: APP/main.php  overrides APP/main/main.php
	 */
	public static function path_find($type = '', $app = APP_NAME){
		if (substr($type,0,1) != '.') $type = empty($type)? EXT : '.'.$type.EXT;
		$false = false;
		$found = file_exists($path = APP.$app.$type) ||
				 file_exists($path = APP.$app.SLASH.$app.$type);		
		if (!$found) return false;
		return $path;
	}

	/**
	 * Application Identifier
	 * Uses de uri to identify the correct app and run it.
	 */
	private static function identify(){
		# remove subdirectories (if any)
		$uri = str_replace(PATH, '/', URI);
		# get safe characters
		if (!$char = parent::config('safe_chars')) error('Missing URI chars');
		# sanitize a little bit, by removing double slashes
		while (strpos($uri,'//')!==false) $uri = str_replace('//','/',$uri);					
		# uri starts with '?' then treat it as a GET request
		if (isset($uri[0]) && $uri[0] == '?'){
			$uri = preg_replace('/[^\&\='.$char.']/','',substr($uri,1));
			foreach(explode('&',$uri) as $v){
				$v = explode('=',$v);
				if (!isset($v[1])) $v[1] = null;
				$var[$v[0]] = $v[1];
			}
			$uri = array('ctrl'=>'__index__','args'=>$var);
		}
		# uri contains slashes, then treat it as a mod_rewriteredirected request.
		elseif ($uri!='/' && strpos($uri,'/')!==false){
			$uri = preg_replace('/[^\/'.$char.']/','',$uri);
			$uri = explode("/", $uri);
			array_shift($uri);
			$ctrl = array_shift($uri);
			# clean empty strings. #### WARNING: QUICKFIX ####
			foreach($uri as $k=>$v) if ($v=='') unset($uri[$k]);
			$uri = array('ctrl'=>$ctrl, 'args'=>$uri);
		}
		# uri is empty, trigger default controller.
		else $uri = array('ctrl'=>'__index__', 'args'=>array());
		return $uri;
	}

	/**
	 * Method Getter
	 * Retrieve method declarations on given class.
	 */
	private static function helpers($class){
	 	$view = new ReflectionClass($class);
	 	$file = parent::file($view->getFileName());
	 	$methods = array();
	 	# get only methods that are not defined here.
	 	$m = array_diff(get_class_methods($class),get_class_methods(__CLASS__));
	 	foreach ($m as $methodname){
	 		$method = $view->getMethod($methodname);
	 		if ($method->isPrivate() || $method->isProtected()) continue;
	 		# ignore methods starting with underscore
	 		if ($methodname[0] == '_') continue;
	 		# obtain method's source [and cleanit a little]
	 		$src = array();
	 		for($i=$method->getStartLine()-1; $i < $method->getEndLine(); $i++){
	 			$line = preg_replace('%[^\S]+(?:#|//).*%','', $file[$i]);
	 			$line = preg_replace('/\s+/',' ',trim($line));
	 			if (!empty($line)) $src[] = $line;
	 		}
	 		# remove visibility declarations
	 		$rx = '/(\s*(?:final|abstract|static|public|private|protected)\s)/i';
	 		$src[0] = preg_replace($rx,'', substr($src[0], 0, strpos($src[0], '{')+1));
	 		$methods[$methodname] = implode(' ', $src);
	 	}
	 	return $methods;
	 }

}