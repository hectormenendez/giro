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
		# if there's no Model instance available pass.
		if (isset($arg[0]) && is_object($arg[0]) && $arg[0] instanceof $model){
			# pass the model as reference
			$this->model = &$arg[0];
		}
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