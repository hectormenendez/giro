<?php

class mainControl extends Control {

## If you want to override how this application loads
## use a native constructor.
##
#	public function __construct($args=false){
#		var_dump(get_func_args())	
#	}

	function main(){
		$args = func_get_args();
		parent::error($this->model->message,'Warning!');
	}

}	