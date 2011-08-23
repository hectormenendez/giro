<?php

/**
 * Library
 * Common methods for all libraries.
 *
 * Before adding any method, take your time and think 
 * if it doesn't belong to Core.
 */
abstract class Library {

	/**
	 * Static calls Catcher
	 * Write some repetetive methods by catching it's name and redirect.
	 */
	 public static function __callStatic($name, $args) {
	 	# error plus header redirector.
	 	if (preg_match('/^(error|warning|notice)_(\d{3})$/i', $name, $match)){
	 		Core::header((int)$match[2]);
	 		call_user_func_array($match[1],$args);
	 		return true;
	 	}
	 	error("Unknown Method '$name'.");
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
	 * Returns the name of the class that's calling.
	 * If the native function returns __CLASS__ it does a lot of parsing to find
	 * out if it's indeed this class calling itself.
	 */
	private static function caller(){
		$rx_call = '/([a-z_][a-z0-9_]*)\s*(?:[:]{2}|[-][>])\s*/i';
		# only if we're not getting deep enough with get_called_class
		$class = get_called_class();
		if ($class != __CLASS__) return $class;
		$trace = debug_backtrace();
		$count = count($trace);
		# find the first trace outside this class.
		for($i=0; $i < $count; $i++)
			if (isset($trace[$i]['class']) && $trace[$i]['class'] !== $class)
				break;
		# start traversing lines of code.
		do {
			# if we reached the end, stop execution and return this class' name.
			if (!isset($trace[$i]) || !isset($trace[$i]['line']))
				return $class;
			$line = $trace[$i]['line']-1;
			$file = $trace[$i]['file'];
			# get file, cached.
			$file = Core::file($file, true, FILE_IGNORE_NEW_LINES);
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
		# we shouldn't get here, but if we do, return this class name,
		return $class;
	}

	private static function _caller($file, $line, $type){
		$rx = '%^[^#/*\w]*class\s+(?<name>[a-z_][a-z0-9_]*)\s*(?:extends\s+(?<ext>[a-z_][a-z0-9_]*))*%i';
		while ($line > 0) {
			$line--;
			# move the pointer upwards matching the word class first and then
			# preging to check if it's in fact a class declaration.
			if (
				stripos($file[$line], 'class') === false ||
				!preg_match($rx, $file[$line], $match)
			) continue;
			# found a match.
			# if the call refers to a parent, point in the right direction.
			if ($type=='parent' && isset($match['ext']) && !empty($match['ext']))
				return $match['ext'];
			# otherwise return the matched class.
			return $match['name'];
		}
		# reached the beginning of the file, sorry mate.
		return __CLASS__;
	}

}
