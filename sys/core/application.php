<?php
abstract class Application {

	/**
	 * Catches all calls to inexistent methods, and prevents issuing errors about it. [for security]
	 * It also serves as a redirector for the  Library class.
	**/
	public function __call($name,$args){
		#$class = strtolower(get_class($this));
		#if (!isset($this->__app[$class])) $this->__app[$class] = new ReflectionClass($class);
		#$app = &$this->_app[$class];
		# if the method exist in Library run it. [beta]
		if (method_exists('Library',$name)) call_user_func_array("Library::$name", $args);
		return false;
	}
}

class Model extends Application {}

class Control extends Application {

	public $model = null;

	/**
	 * Makes the model available for the user.
	 * In theory, the user won't need to use a constructor, since I provide an auto-called method
	 * but I didn't declare this final so the user can override this funcionality..
	 *
	 * @param string $model 
	 * @author Hector Menendez
	 */
	public function __construct(&$model){
		$this->model = &$model;
	}

}
