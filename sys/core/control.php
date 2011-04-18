<?php
class Control extends Application {

	/**
	 * Control shutdown
	 * Make sure all variables declared using the view property are available 
	 * as the main scope on the view file.
	 */
	public function __destruct(){
	 	# If there's a view (it should)  create a pseudo scope and include file.
	 	if (!file_exists(APP_PATH.APP_NAME.'.view'.EXT)) return false;
	 	# obtain all the methods declared on the view class and set them on the
	 	# global scope.
	 	foreach (parent::methods_get($this->view) as $__k => $__v){
	 		if (function_exists($__k)) error("Can't use '$__k' as view-function.");
	 		eval($__v);
	 	}
	 	# now do the same but with vars
	 	foreach (parent::$__vars as $__k => $__v) $$__k = $__v;
	 	# remove unneeded vars, and call the view.
	 	unset($__k,$__v);
	 	include APP_PATH.APP_NAME.'.view'.EXT;
	 	# run the Application queue
	 	parent::queue_run();
	}
}