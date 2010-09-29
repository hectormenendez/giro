<?php
/**
*	Common methods for libraries.
**/
abstract class Library {

	private static $content	= false;
	private static $config	= false;

	/**
	*	Retrieves/validates the config array (or parts of it)
	*
	*	@param	key		string		The config key you desire to look
	*	@param	class	mixed		Calling Class Name (default:auto-look, null:core)
	**/
	public static function &config($key=false, $error=_error, $class=_class){
		//	since we're returning by reference we need to define a false var.
		$false = false;
		if (!$class  = self::class_get($class, $error)) return $false;
		if (!$config = self::configs($error)) return $false;
		//	the class key must exist
		if (!isset($config[$class])) return self::error(array('config_class',$class), false, $error, $class);
		// if no key is specified return the whole array, if it doesn't, send error.
		if (!is_string($key)) return $config[$class];
		else $key = strtolower($key);
		if (!isset($config[$class][$key])) return self::error(array('invalid_config',$key), false, $error, $class);
		//	Verify value is an array and if everything ok, return it.
		return Core::is_type('array', $config[$class][$key], $error, $class);
	}

	/**
	*	Gets a value defined in the Content array.
	*
	*	@param	$key	mixed	Key value.
	*	@param	$class	string	Class name.
	**/
	public static function content($key=false, $error=_error, $class=_class){
		//	make sure Content is loaded
		if (!$class = self::class_get($class, $error)) return false;
		if (!$cont = self::contents($error)) return false;
		if (!$lang = Core::language(false, false, $error)) return false;
		//	return the whole array if no key is specified;
		if (!$key && !is_string($key)) return $cont[$lang][$class];
		if (is_string($key) || is_numeric($key)) return self::_content($key,$class,$cont[$lang]);
		elseif (is_array($key)){
			//	the array must have at least two elements and all of them must be string or number'.
			if (count($key)<2) return self::error(array('array_number','content','2+'), false, $error, $class);
			foreach($key as $k=>$v) {
				if(!is_string($v) && !is_numeric($v))$key[$k] = (string)$v;
				//return self::error('invalid_type', false, $error, $class);
			}
			// the first element of the array will always be the source string
			$src = self::_content((string)array_shift($key),$class,$cont[$lang]);
			// do the replacement
			return vsprintf($src,$key);
		}
		// This would only happen if true is sent as key
		return self::error('invalid_argument', false, $error, null);
	}
	private static function _content($key,$class,$cont){
		$k = strtolower((string)$key);
		//	if the key doesn't exists in class check if exists in core, if not, return the key as is.
		if (!isset($cont[$class][$k])) {
			$core = Core::library();
			$core = reset($core);		
			return isset($cont[$core][$k])? $cont[$core][$k] : $key;
		}
		return $cont[$class][$k];
	}

	/**
	*	Returns / Validates the main config array
	**/
	public static function &configs($error=_error){
		return self::setting('config', false, $error);
	}

	/**
	*	Returns / Validates the main content array
	**/
	public static function &contents($error=_error){
		return self::setting('content', false, $error);
	}

	/**
	*	Returns / Validates an array of Settings (used by configs and contents)
	*
	*	@param	key 	string
	*	@param 	path	string	A valid path were the file resides. ($key.EXT)
	**/
	public static function &setting($key=false, $path=false, $error=_error){
		// if no path is provided assume core path
		if (empty($path)) $path = CORE;
		if (!is_string($key) || !isset(self::$$key))
			return self::error(array('invalid_setting',$key),false, $error, null);
		//	return if already defined
		if (is_array(self::$$key)) return self::$$key;
		//	Set and validate contents-
		if (!($parsed = Core::parse($path.$key.EXT,true)))
			return self::error('', $key.' error', $error, null);
		if (!is_array($parsed) || count($parsed)<1)
			return self::error('invalid_'.$key, false, $error, null);
		foreach ($parsed as $arr) if (!is_array($arr))
			return self::error('invalid_'.$key.'_key', false, $error, null);
		// all good, set and return
		self::$$key = $parsed;
		return self::$$key;
	}

	/**
	*	Returns/validates Config-specific options, it returns the first element if no key is specified.
	*
	*	@param	cfg		mixed		config key you want to get. (if nothing specified returns first)
	*	@param key		string		option key you want to get. (if nothing specified returns first)
	*	@param	ret		bool		Return value by default, key if false, or array(key/value) if true.
	*	@param	type	mixed		Check for variable type (default:null=no-check, false=array)
	**/
	public static function option($cfg=false, $key=false, $type=null, $ret=null, $error=_error, $class=_class){
		if (!$class  = self::class_get($class, $error)) return false;
		if (!$config = self::config($cfg, $error, $class)) return false;
		//	if no key is specified use default (first)
		if (!$key){ reset($config); $key = key($config); }
		else $key = strtolower($key);
		//	check if the key exists and/or its type.
		if (!isset($config[$key])) return self::error(array('invalid_'.$cfg, $key), false, $error, $class);
		Core::is_type($type, $config[$key], $key, $class);
		// send value by default, key if false, or key/value if true.
		return is_null($ret)? $config[$key] : ($ret!==true? $key : array($key,$config[$key]));
	}

	public static function option_set(&$var, $cfg=false, $key=false, $type=null, $ret=null, $error=_error, $class=_class){
		//	if a key is specified or a current property hasn't been declared set it.
		if (!$v = self::option($cfg,$key,$type,true,$error,$class)) return false;
		$var = $v[0];
		return is_null($ret)? $v[1] : ($ret!==true? $v[0] : array($v[0],$v[1]));
	}

	/**
	*	Handles errors. if no error library is available sends a basic one.
	*
	*	@param	msg		mixed	Error message, empty by default.
	*	@param	tit		mixed	Error title. 'Error' by default (can be replaced in content)
	**/
	public static function &error($msg=false, $tit=false, $error=_error, $class=_class){
		//	all core methods have the option to hide erros and return false instead.
		//	Since some of those return by reference, we need to do it here too, hence the variable $tmp
		if ($error!==true) { $tmp = false; return $tmp; }
		//	we cannot send errors within this method (would generate recursion).
		if (!$class = self::class_get($class,false)) $class = strtolower(__CLASS__);
		//	if error library available, redirect error.
		if (Core::library('error',false,false)) Error::show($msg, $tit, $class);
		//	In case the library isn't available send a simple error (converting arrays to strings)
		error($msg,$tit);
	}

	/**	
	*	Checks if given class name is valid. if nothing specified, returns calling class name.
	*
	*	@param		class	string	Class name
	**/
	public static function class_get($class=false, $error=_error){
		//	if string provided, verify class files exist.
		if (is_string($class)){
			if (!Core::library($class,true,false))
				return self::error(array('invalid_class',$class),false,$error,null);
			return $class;
		}
		//	find out the class name, unless null is provided.
		if ($class!==null){
			//	As of php 5.3 we have this amazing function easing the process a lot!
			if (function_exists('get_called_class')) return strtolower(get_called_class());
			//	Sadly 5.3< will have to find the name loading the calling file to an array and preg_matching
			$class = self::_class_get();
		}
		//	if by now we don't have a class name, assume Core.
		if (!is_string($class)){
			$lib = Core::library();
			return reset($lib);
		}
		return $class;
	}
	/**
	*	IMPORTANT NOTE: I'm aware that using this method is not reliable and cpu intensive, but I haven't
	*					found another way to mimic get_called_class' behaviour prior PHP 5.3.
	*
	**/
	private static function _class_get(){
		static $cache = array();
		$vv = '[a-zA-Z\_][a-zA-Z0-9\_]+';
		$bt = debug_backtrace();
		//	use the first trace outside this file.
		foreach($bt as $trace){
			if ($trace['file'] === __FILE__) continue;
			break;
		}
		$l = $trace['line'];
		$f = $trace['file'];
		//	if line in file has been parsed before, return cached class name.
		//	this would help to save cpu when calling from a loop
		if (isset($cache[$f][$l])) return $cache[$f][$l];
		//	getting the file contents is expensive, cache each file converted
		if (isset($cache[$f]['array'])) $file = $cache[$f]['array'];
		else $file = $cache[$f]['array'] = file($f);
		//	if the specified line of code doesn't have a valid class call, force Core.
		if (!preg_match('/('.$vv.')\:{2}'.$trace['function'].'/',$file[$l-1], $match)) return null;
		$match = strtolower($match[1]);
		//	matched declaration, but wait, is it self or parent?
		if ($match=='self' || $match=='parent') {
			//	avoiding re-seeking for a class declaration
			if (isset($cache[$f]['self'])) return $cache[$f]['self'];
			$match = $cache[$f]['self'] = self::_class_seek($l, $file, $vv);
		}
		return $cache[$f][$l] = $match;
	}
	//	seeks upwards for a class declaration
	private static function _class_seek($l, $file, &$preg){
		$l--;
		while ($l>0 && strpos($file[$l],'class')===false) {
			$l--;
		}
		if ($l<=1) return null;
		if (!preg_match('/^[\s]*class[\s]+('.$preg.')/si', $file[$l], $match))
			return self::_class_seek($l, $file, $preg);
		return strtolower($match[1]);
	}
}