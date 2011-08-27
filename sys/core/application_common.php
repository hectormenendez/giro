<?php
/**
 * Common methods for Models, Views and Controllers
 *
 * @created 2011/AUG/23:57
 */
class Application_Common extends Library {

	/**
	 * Verify if given model is a Model.
	 *
	 * @log     2011/AUG/26 23:58 Moved from Application_Model
	 * @working 2011/AUG/25 18:15
	 * @created 2011/AUG/25 18:11
	 */
	protected static function is_model($app=false){
		return 	is_object($app) && $app instanceof Model;
	}

	/**
	 * Verify if given model is a View
	 *
	 * @created 2011/AUG/27 00:01
	 */
	protected static function is_view($app=false){
		return 	is_object($app) && $app instanceof View;
	}

	/**
	 * Verify if given model is a Controller
	 *
	 * @created 2011/AUG/27 00:01
	 */
	protected static function is_control($app=false){
		return 	is_object($app) && $app instanceof Control;
	}
	
}