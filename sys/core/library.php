<?php

/**
 * Library
 * Common methods for all libraries.
 */
abstract class Library {

	/**
	 * What's app?
	 * Returns the currently running application name.
	 *
	 * @todo is this really necessary? I mean, using this is just a way of c
	 *		 checking if the constat is defined. Anyways, ponder it later.
	 */
	public static function application(){
		if (!defined('APPNAME')) error('An application has not run yet');
		return APPNAME;
	}

	/**
	 * Config Key Getter.
	 * Retrieves a key from the main config array, it detect the calling class
	 * so you can only have to specify the key, and you're good to go.
	 * If called with no arguments, retrieves the whole config array.
	 *
	 * @todo	Set keys from here
 	 */
	public static function &config($key=false, $class=false){
		$false = false;	
		if (!$class = self::class_called($class)) return $false;
		$conf = Core::config_get($class);		
		if (!is_string($key)) return $conf;
		if (!isset($conf[$key])) return $false;
		return $conf[$key];
	}


	/**
	 * Child Detector
	 * Who's calling the base? 
	 */
	private static function class_called($class=false){
		if ($class === null) return 'core';
		return strtolower(get_called_class());
	}
	
}