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
	 * Config Key Getter / Setter
	 * Specify the key [and optinally a value], and you're good to go.
	 * It automatically detects the lib you're in; Although you can specify one.
	 * If called with no arguments, retrieves the whole config array.
	 *
	 * @todo	Set config keys from here
 	 */
	public static function &config($key = false, $val = null, $class = false){
		$false = false;	
		$class = strtolower(is_string($class)? $class : self::caller());
		# GET Mode
		if ($val === null){
			# no class key is still defined, return false.
			if (!$conf = &Core::config_get($class)) return $false;
			# no key specified, return the class' array
			if (!is_string($key)) return $conf;
			if (!isset($conf[$key])) return $false;
			return $conf[$key];
		}
		# SET MODE
		if (!is_string($key)) return $false;
		$conf = &Core::config_get(); # whole array reference
		if (!isset($conf[$class])) $conf[$class] = array();
		$conf[$class][$key] = $val;
		return $conf[$class][$key];
	}


	/**
	 * Child Detector
	 * At first I thought get_called_class would solve all my problems, but 
	 * turns out it didn't, the framework has gotten tricky. If we want to 
	 */
	private static function caller(){
		static $cache = array();
		$rx_call = '/([a-z_][a-z0-9_]*)\s*(?:[:]{2}|[-][>])\s*/i';
		# only if we're not getting deep enough with get_called_class
		$class = get_called_class();
		if ($class != __CLASS__) return $class;
		$trace = debug_backtrace();
		$count = count($trace);
		#print_r($trace);
		# exit this class.
		for($i=0; $i < $count; $i++)
			if (isset($trace[$i]['class']) && $trace[$i]['class'] !== $class)
				break;			
		do {
			# if we reached the end, sorry. bettr luck next time.
			if (!isset($trace[$i]) || !isset($trace[$i]['line']))
				return $class;
			$line = $trace[$i]['line']-1;
			$file = $trace[$i]['file'];
			# cache file
			$file = isset($cache[$file]['array'])?
				$cache[$file]['array'] : file($file);
			# there must be a valid object calling (:: ->)
			if (preg_match($rx_call, $file[$line], $match) ){
				$tmp = strtolower($match[1]);
				# if there's a class specified, look no further.
				if ($tmp != 'this' && $tmp != 'self' && $tmp != 'parent')
					return $match[1];
				# damn, seek inside the file for a class declaration.
				return self::_caller($file, $line, $tmp);
			}
		} while (++$i);
		# we should get here, but if we do, return this class name,
		return $class;
	}

	private static function _caller($file, $line, $type){
		#move the pointer the class declaration.
		while ((--$line) > 0 && stripos($file[$line], 'class') === false);
		if ($line <= 0 ) return __CLASS__;
		# if this is not a valid call [ie: a comment] continue looking.
		# using recursion ofcourse.
		$rx = '%^[^#/*\w]*class\s+(?<name>[a-z_][a-z0-9_]*)\s*(?:extends\s+(?<ext>[a-z_][a-z0-9_]*))*%i';
		if (!preg_match($rx, $file[$line], $match))
			return self::_caller($line, $file, $type);
		# if the call refers to a parent, point in the right direction.
		if ($type=='parent' && isset($match['ext']) && !empty($match['ext']))
			return $match['ext'];
		# otherwise return the match.
		return $match['name'];
	}
	
}