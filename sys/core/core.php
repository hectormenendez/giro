<?php

abstract class Core extends Library {

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
	*	Library autoloader/checker. Used without arguments returns array of loaded libraries.
	*
	*	@param	$class	string	The name of the class to load/check.
	*	@param	$inclde	bool	Set to true if you don't want to load the class, but only check if exists.
	*	@param	$error	bool	wether to send an error, or just return false.
	**/
	public static function library($class=false, $include=true, $error=_error){
		//	if no class specified return an array of loaded libraries.
		if (!is_string($class)) return self::$library;
		//	check wether the class has been loaded before or not.
		$c = strtolower($class);
		if (in_array($c,self::$library)) return true;
		if ($include!==true) return false;
		//	The class must reside inside the libs or within a folder with the same name
		//	the file has precedence over the directory.
		if (!file_exists($lib=(LIBS.$c.EXT)) && !file_exists($lib=LIBS.$c.SLASH.$c.EXT))
			return self::error(array('invalid_class',$class),false, $error, null);
		// Create a constant holding the path for this Library.
		define('LIB_'.strtoupper($c), str_ireplace($c.EXT, '', $lib));
		//	include the file and flag it as loaded
		include $lib;
		self::$library[] = $c;
		//	If the lib has a pseudo constructor, call it.
		if (method_exists($c,'_construct')) call_user_func("$c::_construct");
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