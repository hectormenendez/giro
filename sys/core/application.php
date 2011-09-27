<?php
/**
 * Application Management.
 */
class Application extends Library {

	private static $default       = null;
	private static $routes        = null;
	private static $application   = null;
	private static $view;

	public static function _construct(){

		if (!is_array(self::$routes = self::config('routes')))
			error('Bad Routing Configuration.');
		if (!is_string(self::$default = self::config('default')))
			error('Bad Default App Configuration.');
	}

	/**
	 * Application Loader
	 * Checks if an application and its dependant model exists for a given URI,
	 * if so, loads the Controller & Model Classes so they handle the hussle.
	 */
	public static function load(){
		if (!defined('URI')) error('The URI has not been parsed yet.');
		# detect if an external file is being requested.
		if (strpos(URI, PUB_URL)!==false) return new Application_External();
		# $ctrl & $args come from here.
		foreach(self::identify() as $k=>$v) $$k=$v;
		# make sure the controller exists
		if (!$path = self::path('', $ctrl)) {
			if ($ctrl == self::$default) parent::error_500('Default Application Missing');
			parent::error_404(ucfirst($ctrl).' does not exist.');
		}
		# controller exists, define constants
		define('APP_PATH', pathinfo($path, PATHINFO_DIRNAME).SLASH);
		define('APP_NAME', $ctrl);
		define('APP_URL', URL.APP_NAME.SLASH);
		# application assamble:
		# first model and view, then controller.
		$null = null;
		$modl              = self::assamble('model',$null, $null);
		$view              = self::assamble('view' ,$modl, $null);
		self::$application = self::assamble($args  ,$modl, $view);
		# render default view after load
		# user can override this anytime.
		if (isset(self::$application->view))self::$application->view->render();
	}

	/**
	 * Application Path Finder
	 * Attempt to load the controller.[files have priority over directories]
	 * ie: APP/main.php  overrides APP/main/main.php
	 *
	 * @param string $type     specifies a different extension.
	 * @param string  $app     specifies a different app name.
	 *
	 * @return mixed           Full path or false.
	 *
	 * @log 2011/AUG/26 18:43  Changed defaults and added commentary.
	 */
	public static function path($type=EXT, $app = APP_NAME){
		if (!is_string($type)) $type = '';
		if (substr($type,0,1) != '.') $type = empty($type)? EXT : '.'.$type.EXT;
		$found = file_exists($path = APP.$app.$type) ||
				 file_exists($path = APP.$app.SLASH.$app.$type);
		if (!$found) return false;
		return $path;
	}

	/**
	 * Application Constructor
	 * Instantiates the application and sets it up.
	 */
	private static function &assamble($args=null, &$model=null, &$view=null){
		$false = false;
		# if an array is sent as first parameter assume controller.
		# the path checking for it is already done in the loader.
		$type = is_array($args)? 'control' : $args;
		# determine if a normal model or a view exists
		$normal = self::path($type != 'control'? $type : null);
		# determine if a common MVCs is available. ie: _model.php _view.php.
		$common = file_exists(APP."_$type".EXT)? APP."_$type".EXT : false;
		# the instance name will be the type unless a model or view exists
		# in wich case we'll include.
		$inst = $normal? APP_NAME.ucfirst($type) : $type;
		# use the common as intermediarie for the parent class
		# or if inexistent, create one on the fly
		if (($common && $normal) || ($common && !$normal)) include $common;
		else eval("class $type extends application_$type {}");
		if ($normal) include $normal;
		# now make sure the instance is correctly declared, and if it is instance it.
		if (!class_exists($inst, false)) error('Invalid '.ucfirst($type).' Declaration.');
		# instantiate the class and send uri parts to constructor.
		$inst = new $inst($args);
		# fill out controller.
		if ($inst instanceof Control){
			$inst->view  = &$view;
			$inst->model = &$model;
			# if the user sends actions, check if a public method named like
			# those actions exist and call it instead, otherwise, carry on.
			$count = count($args);
			for ($i=0; $i<$count; $i++){
				$copy = $i==0? $args : array_slice($args,0,-1*$i);
				$name = join('_', $copy);
				if (self::ispublic($inst, $name)){
					call_user_func_array(array($inst, $name), array_slice($args,$count-$i));
					return $inst;
				}
			}
		}
		elseif ($inst instanceof View) $inst->model = &$model;
		# run pseudo constructor
		if (method_exists($inst, APP_NAME))
			call_user_func_array(array($inst, APP_NAME), (array)$args);
		return $inst;
	}

	/**
	 * Find a public method. Hacky, but short and easy to understand.
	 * @author Hector Menendez <h@cun.mx>
	 * @licence http://etor.mx/licence.txt
	 * @created 2011/SEP/27 11:32
	 */
	private static function ispublic(&$instance, $name){
		(
			$name == APP_NAME
		||
			# ignore methods whose name starts like the app itself.
			substr($name, 0, strpos($name, '_')) == APP_NAME
		||
			!method_exists($instance, $name)
		||
			(
				($is = new ReflectionMethod($instance, $name))
			&&
				($is = $is->isPublic())
			)
		);
		return !empty($is);
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
		# check if any custom route matches.
		$tmpuri = $uri[0]=='/'? substr($uri,1) : $uri; # remove root
		foreach(self::$routes as $rx=>$route){
			try {
				if (!preg_match($rx, $tmpuri)) continue;
				$uri = '/'.preg_replace($rx, $route, $tmpuri);
				break;
			} catch (Exception $e) {
				error('Incorrect Routing Declaration');
			}
		}
		# uri starts with '?' then treat it as a GET request
		if (isset($uri[0]) && $uri[0] == '?'){
			$uri = preg_replace('/[^\&\='.$char.']/','',substr($uri,1));
			foreach(explode('&',$uri) as $v){
				$v = explode('=',$v);
				if (!isset($v[1])) $v[1] = null;
				$var[$v[0]] = $v[1];
			}
			$uri = array('ctrl'=>self::$default,'args'=>$var);
		}
		# uri contains slashes, then treat it as a mod_rewriteredirected request.
		elseif ($uri!='/' && strpos($uri,'/')!==false){
			$uri = preg_replace('/[^\/'.$char.']/','',$uri);
			$uri = explode("/", $uri);
			array_shift($uri);
			$ctrl = array_shift($uri);
			# clean empty strings. #### WARNING: QUICKFIX ###
			foreach($uri as $k=>$v) if ($v=='') unset($uri[$k]);
			$uri = array('ctrl'=>$ctrl, 'args'=>$uri);
		}
		# uri is empty, trigger default controller.
		else $uri = array('ctrl'=>self::$default, 'args'=>array());
		return $uri;
	}


}
