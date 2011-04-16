<?php
class Application {

	public static $__vars = array();

	/**
	 * fallback to Library class or return null.
	 */
	public function __call($name, $args){
		if (method_exists('Library', $name))
			return call_user_func_array("Library::$name", $args);
		return null;
	}

}

class Model extends Application {}

class Control extends Application {

	/**
	 * Run View on script shutdown
	 * Make sure all variables declared using the view property are available 
	 * as the main scope on the view file.
	 */
	public function __destruct(){
	 	# If there's a view (it should)  create a pseudo scope and include file.
	 	if (!file_exists(APP_PATH.APP_NAME.'.view'.EXT)) return false;

	 	foreach (parent::$__vars as $__k => $__v) $$__k = $__v;
	 	unset($__k,$__v);
	 	include APP_PATH.APP_NAME.'.view'.EXT;
	}

}

class View extends Application {

	/**
	 * Allow the user to store variables indistinctively.
	 * They will be later put un view's scope.
	 */
	public function &__set($key, $val){
		parent::$__vars[$key] = $val;
		return parent::$__vars[$key];
	}
	
}