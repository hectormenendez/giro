<?php

/**
 * Library
 * Common methods for all libraries.
 */
abstract class Library {

	/**
	 * Error Manager.
	 * This will eventually be an error manager, for now it's just a redirector
	 * to a VERY simple function defined on ROOT/index.EXT.
	 *
	 * @param [string] $msg		Error Title.
	 * @param [string] $title	Error Title.
	 */
	public static function error($msg=false, $title=false){
		return error($msg, $title);
	}

	/**
	 * Config Key Getter.
	 * Retrieves a key from the main config array, it detect the calling class
	 * so you can only have to specify the key, and you're good to go.
	 * If called with no arguments, retrieves the whole config array.
 	 */
	public static function &config($key=false, $class=false){
		$false = false;	
		if (!$class = self::class_detect($class)) return $false;
		$conf = Core::config_get($class);		
		if (!is_string($key)) return $conf;
		if (!isset($conf[$key])) return $false;
		return $conf[$key];
	}


	/**
	 * Child Detector
	 * Who's calling the base? 
	 */
	private static function class_detect($class=false){
		if ($class === null) return 'core';
		return strtolower(get_called_class());
	}
	
}