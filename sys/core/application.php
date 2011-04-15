<?php

class Model extends Application {}
class Control extends Application {}

class Application {

	/**
	 * Sets instantiates the model on the controller.
	 */
	public function __construct(){
		$arg = func_get_args();
		# determine the actual application name, and append Model to it.
		$app = get_called_class();
		$app = str_replace('Control','', str_replace('Model','',$app));
		$model = $app.'Model';
		# if there's a Model instance pass it to the controller as reference.
		if (isset($arg[0]) && is_object($arg[0]) && $arg[0] instanceof $model)
			$this->model = &$arg[0];
		# define the current appname as a constant so it can be later accessed 
		# by the Library class, or the user for that matter.
		if (!defined('APPNAME')) define('APPNAME', $app);
	}

	/**
	 * fallback to Library class
	 */
	public function __call($name, $args){
		if (method_exists('Library', $name))
			return call_user_func_array("Library::$name", $args);
		return $name;
	}

	#public static function _construct(){}

}