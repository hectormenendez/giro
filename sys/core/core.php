<?php
/**
 * CORE Library.
 *
 * @version		v2.0r2 [25/SEP/2010]
 * @author 		Hector Menendez
 */
abstract class Core extends Library {

	const _CONSTRUCT = '_construct'; # method that wll work as pseudo-constructor for abstract classes.

	private static $library		= array('core');	// don't modify
	private static $language	= false;
	private static $charset		= false;

	public static function _construct(){
		//	verify config and content.
		self::config();
		self::content();
		//	Autoload methods and functions through Core
		spl_autoload_register('self::library');
		// Custom shutdown handler (
		register_shutdown_function('Core::shutdown');
		//	startup required libraries
		foreach (self::config('startup') as $lib) self::library($lib);
	}

	/**
	*	Validates/sets_current language key (default one, if nothing specified)
	*
	*	@param	key	string	language code to retrieve.
	*	@param val	bool	wether to retrieve language value or key.
	**/
	public static function language($key=false, $return=null, $error=_error){
		return self::option_set(self::$language,'language', $key, 'string', $return, $error, null);
	}

	/**
	*	Returns/validate charset key (or default one)
	*
	*	@param	key	string	Existing key name
	*	@param	value	bool	If set to false, returns the keyname instead of value.
	*	@see	Core::option
	**/
	public static function charset($key=false, $return=null, $error=_error){
		return self::option_set(self::$charset,'charset', $key, 'string', $return, $error, null);
	}

	/**
	* Library autoloader/checker.
	* Autodetermines where classes are located and make them available. runs pseudo-constructors if avail.
	* Used without arguments returns array of loaded libraries.
	*
	* @note		Files have precedence over directories. 
	*
	* @param	string 	$class		The name of the class to load/check.
	* @param	bool	$checkonly	if true, won't load anything, just check for file existance.
	* @param	bool	$error		wether to send an error, or just return false.
	*
	* @version	v2 [29/SEP/2010]
	* @log		The last fix was not really working, so I rewritten the method again. Removed Controller/Model
	*			functionallity, and moved it back to application::load. [it was an overkill here].
	* @log		If $class has keywords: Control/Model at the end, then try to load from APP folder, if not
	*			found, fall back to original funcionality and try to load class from LIB.
	**/
	public static function library($name=false, $checkonly=false, $error=_error){
		# if no name provided, just return loaded libraries array.
		if (!is_string($name)) return self::$library;
		$name = strtolower($name);
		# no need to load an lready loaded lib, right?
		if (in_array($name,self::$library)) return true;
		# is it a CORE module?
		if (file_exists($lib=CORE.$name.EXT)) $found = 'CORE';
		# Check for file inside LIBS/name.EXT or LIBS/name/name.EXT
		elseif ((file_exists($lib=LIBS.$name.EXT)||file_exists($lib=LIBS.$name.SLASH.$name.EXT)))
			$found = 'LIB';
		# Nothing? don't load anything then.
		else $found = false;
		# At this point we know if we found a library or not. if this is a checkonly call return state.
		if ($checkonly) return $found? true : false;
		# we found something, include it and define a constant holding its path.
		if ($found){
			include $lib;
			if (!defined($const=strtoupper($found.'_'.$name))) define($const,pathinfo($lib,PATHINFO_DIRNAME));
			self::$library[] = $name;
		}
		# send [or return] an error if the file was not found or if the correct class name is not set.
		if (!$found || ($found==true && !class_exists($name,false)))
			return self::error(array('invalid_class',$name),'autoloader',$error);
		# finally, if the class has a static pseudo-constructor declared, call it.
		if (method_exists($name,'_construct')) call_user_func("$name::_construct");
		return true;
	}

	/**
	*	Run pseudo destructors [if declared] on our library.
	**/
	public static function shutdown(){
		$library = array_reverse(self::$library);
		foreach($library as $class){
			if (method_exists($class,'_destruct')) call_user_func("$class::_destruct");
		}
		return;
	}

	/**
	*	Check syntax of php file before actually including it. (use it only when really really necessary)
	*
	*	@param	$file	mixed	Path of the file to include.
	*	@param $inc		bool	include the file, or return evaluated code. (includes it by default)
	**/
	public static function parse($file, $return=false){
		if (!file_exists($file)) return false;
		//	generate a tmp file name
		$tmp = TMP.substr($file,($pos=strrpos($file,SLASH))+1,strlen($file)-$pos).'.parse';
		$mod = filemtime($file);
		//	include without parsinf if $file has not been modified
		if (file_exists($tmp)){
			if ((int)file_get_contents($tmp)==$mod) {
				if ($return===true) return include($file);
				include ($file);
				return true;
			}
		}
		//	The file has beeen modified or never parsed, Get file contents and strip php tags
		$code = preg_replace('/(^|\?>).*?(<\?php|$)/i','',file_get_contents($file));
		//	Create a lambda function to check code validity
		if (!is_string($fn = @create_function('',$code))) return false;
		//	No errors, create/overwrite a tmp file storing $file's modification time.
		file_put_contents($tmp, $mod);
		//	return or include code.
		if ($return===true) return eval($code);
		unset($fn);
		include $file;
		return true;
	}

	/**
	*	Wrapper for native file-type-checking functions.
	*
	*	@param	$type	mixed	What type are we looking? false/0/'' assumes array.
	*	@param	$var			A reference of the variable to look.
	**/
	public static function &is_type($type=null, $var=false, $error=_error, $class=_class){
		//	abort type check if type=null, or assume array if false
		if ($type===null) return $var; elseif(!$type) $type = 'array';
		//	Validate function and type
		if (!function_exists($fn = 'is_'.strtolower($type)))
			return self::error(array('invalid_function',$fn), false, $error, $class);
		if (call_user_func($fn,$var)==false)
			return self::error(array('invalid_type',$type), false, $error, $class);
		return $var;
	}
}