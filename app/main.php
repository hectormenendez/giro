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
		$app = isset($args[0])? $args[0] : '';

		if ($app=='docs') return docs::control($args);

		parent::error('Mantenimiento en Proceso','Warning!');
	}

}

