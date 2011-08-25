<?php
/**
 * Default controller
 *
 * @log 2011/AUG/24 21:17 Removed unnecessary code
 */
class mainControl extends Control {

	function main(){
		core::config('debug',false);
		notice(WHOAMI);
	}

}
