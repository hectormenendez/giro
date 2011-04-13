<?php
# If the framework is not loaded yet, include it.
# traverse-back folders until we find the main gFW caller.
if (!defined('IS_INC')){
	$path = pathinfo(__FILE__,PATHINFO_DIRNAME);
	$file = '/index.php';
	while (!empty($path)){
		$path = substr($path,0,strrpos($path,'/'));
		if (file_exists($path.$file)) break;
	}
	include $path.$file;
	# Now that we have the Framework ready, instantiate this file, but now declared as a controller.
	Core::application('main::external');
	return true;
}

class MainExternalControl extends Control {

	public function external(){
		if ($this->model) echo "ExternalController \n {$this->model->data}\n";
	}

}