<?php
/**
 * Rendering Methods.
 *
 * @todo Make this library handle its own JS and css themes
 *
 * @working 2011/AUG/28 00:00
 * @created 2011/AUG/27 02:39
 */
final class Auth_View extends Library {

	/**
	 * Load required view
	 *
	 * @note given the way methods are converted to functions [serializing]
	 *       trying to pass the PDO object to the view would generate an exception.
	 *
	 * @working 2011/AUG/28 00:03  
	 * @created 2011/AUG/27 04:21
	 */
	public function __construct(&$model){
		# if NULL, user tried to log in but failed.
		define('AUTH_FAILED', $model->logged === null);
	}


	/**
	 * Output Login form.
	 *
	 * @working 2011/AUG/2011 00:01
	 * @created 2011/AUG/2011 03:55
	 */
	public function render(){
		if (!file_exists($_PATH = AUTH.'auth.html'))
			error('Auth HTML is missing');
		ob_start();
		include $_PATH;
		return ob_get_clean();
	}

}