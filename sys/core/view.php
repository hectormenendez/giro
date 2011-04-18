<?php
/**
 * View Manager.
 * This methods will be available as normal functions inside the view file
 * private, protected, and methods starting with an underscore will be ignored.
 *
 * @note Remember that these function will run in the global scope, 
 * 		 so dont treat them as class members, technically speaking, they won't 
 *		 be inside any class.
 */
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