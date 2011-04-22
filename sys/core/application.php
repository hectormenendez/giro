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
	 * Application Loader
	 * Checks if an application and its dependant model exists for a given URI,
	 * if so, loads the Controller & Model Classes so they handle the hussle.
	 *
	 * @param [array] $uri Internal array containing the ruting info.
	 *
	 * @return [mixed][reference] Application controlller.
	 */
	public static function load(){
		if (!defined('URI')) error('The URI has not been parsed yet.');
		if (!self::$default = parent::config('default'))
			error('Default APP set incorrectly');
		$uri = self::identify();
		$ctrl = $uri['ctrl'] == '__index__'? self::$default : $uri['ctrl'];
		$args = $uri['args'];
		unset($uri);
		# Attempt to load the controller.[files have priority over directories]
		# ie: APP/main.php  overrides APP/main/main.php
		$found = file_exists($path_ctrl = APP.$ctrl.EXT) ||
				 file_exists($path_ctrl = APP.$ctrl.SLASH.$ctrl.EXT);
		if (!$found)
			parent::error_404($ctrl == self::$default? 'Index 404' : false);
		# controller exists, define constants
		define('APP_PATH', pathinfo($path_ctrl, PATHINFO_DIRNAME).SLASH);
		define('APP_NAME', $ctrl);
		define('APP_URL', URL.APP_NAME.SLASH);
		unset($ctrl);
		# if a model exists, load it first.
		self::$application = self::construct(true, $args, self::construct(0));
		self::destruct();
	}

	/**
	 * Application Unloader
	 * Destroys application temp data after a timeout.
	 */
	public static function unload(){
		if (!defined('APP_NAME')) return false;
		if (!$time = self::config('clean_timeout')) $time = 10;
		shell_exec("nohup php -r \"sleep(".$time."); @unlink('".TMP.UUID.'.'.APP_NAME."');\" > /dev/null & echo $!");
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
	 * Application Destructor
	 * This will run as soon as the controller ends its execution.
	 */
	private static function destruct(){
		self::render();
		self::queue_run();
	}

	/**
	 * Application Constructor
	 * Instantiates the application and sets it up.
	 */
	private static function &construct($x=true, $args=null, &$model=null){
		$false = false;
		$found = file_exists($path = APP.APP_NAME.($x? '':'.model').EXT) ||
				 file_exists($path = APP.APP_NAME.SLASH.($x? '':'model').EXT);		
		if (!$found)  return $false;
		include $path;
		$inst = APP_NAME.($x? 'Control' : 'Model');
		if (!class_exists($inst, false)) error("Invalid App Declaration.");
		$inst = new $inst($args);
		# instantiate view and model if this is a controller
		if ($x){
			$inst->view = new View;
			$inst->model = &$model;
			return $inst;
		}
		# run pseudo constructor
		if (method_exists($inst, APP_NAME))
			call_user_func_array(array($inst, APP_NAME), (array)$args);
		return $inst;
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
			$uri = array('ctrl'=>array_shift($uri), 'args'=>$uri);
		}
		# uri is empty, trigger default controller.
		else $uri = array('ctrl'=>'__index__', 'args'=>array());
		return $uri;
	}

	/**
	 * Render View
	 * Creates an encapsulated scope so the view and extenal files can share it.
	 */
	private static function render(){
	 	if (!file_exists(APP_PATH.APP_NAME.'.view'.EXT)) return false;
	 	# obtain all methods declared on the view set them on the global scope.
	 	foreach (self::helpers(self::$application->view) as $__k => $__v){
	 		if (function_exists($__k)) error("Can't use '$__k' as view-function.");
	 		eval($__v);
	 	}
	 	# now, we make available all the variabes in the global scope for the 
	 	# view, and for the included css and js, by using a temp php that we'll
	 	# include here and there.
	 	$__s = "<?php\n"
	 		 .	"\$__s = unserialize(stripslashes(\"". addslashes(serialize(View::$__vars))."\"));\n"
	 		 .  "foreach ( \$__s as \$__k => \$__v) \$__s .= \$\$__k = \$__v;\n"
	 		 .  "unset(\$__s,\$__k,\$__v);\n";
	 	file_put_contents(TMP.UUID.'.'.APP_NAME, $__s);
	 	# remove unneeded vars, and call the view.
	 	unset($__s,$__k,$__v);
	 	include TMP.UUID.'.'.APP_NAME;
	 	include APP_PATH.APP_NAME.'.view'.EXT;
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