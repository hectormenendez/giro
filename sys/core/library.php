<?php

/**
 * Library
 * Common methods for all libraries.
 */
abstract class Library {

	/**
	 * Static calls Catcher
	 * Write some repetetive methods by catching it's name and redirect.
	 */
	 public static function __callStatic($name, $args) {
	 	# error plus header redirector.
	 	if (preg_match('/^(error|warning|notice)_(\d{3})$/i', $name, $match)){
	 		self::header((int)$match[2]);
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

	private static $file = array();

	/**
	 * Simple File Manager
	 * Stores files in a static var so they can be constantly accessed.
	 *
	 * @param [string]$path	The file path, it will be used to identify the file.
	 * @param [bool]$array 	Array or plain file?
	 * @param [int]$flags 	FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES
	 *
	 * @return	[mixed]	
	 */
	public static function file($path=null, $array=true, $flags=0){
		if ($path === null) return self::$file;
		if (!file_exists($path)) return false;
		$key = $path;
		$mode = $array? 'array' : 'string';
		if (isset(self::$file[$key][$mode])) return self::$file[$key][$mode];
		if (!isset(self::$file[$key])) self::$file[$key] = array();
		self::$file[$key][$mode] = $array?
			file($path, $flags) : 
			file_get_contents($path);
		return self::$file[$key][$mode];
	}

	/**
	 * Header Shorthand
	 */
	public static function header($code = null){
		if (headers_sent()) return false;
		if (is_int($code)){
			switch((int)$code):
			case 200: $head = 'HTTP/1.1 200 OK';							break;
			case 304: $head = 'HTTP/1.0 304 Not Modified';					break;
			case 400: $head = 'HTTP/1.0 400 Bad request'; 					break;
			case 401: $head = 'HTTP/1.0 401 Authorization required';		break;
			case 402: $head = 'HTTP/1.0 402 Payment required'; 				break;
			case 403: $head = 'HTTP/1.0 403 Forbidden'; 					break;
			case 404: $head = 'HTTP/1.0 404 Not found';						break;
			case 405: $head = 'HTTP/1.0 405 Method not allowed';			break;
			case 406: $head = 'HTTP/1.0 406 Not acceptable';				break;
			case 407: $head = 'HTTP/1.0 407 Proxy authentication required'; break;
			case 408: $head = 'HTTP/1.0 408 Request timeout';				break;
			case 409: $head = 'HTTP/1.0 409 Conflict';						break;
			case 410: $head = 'HTTP/1.0 410 Gone';							break;
			case 411: $head = 'HTTP/1.0 411 Length required';				break;
			case 412: $head = 'HTTP/1.0 412 Precondition failed';			break;
			case 413: $head = 'HTTP/1.0 413 Request entity too large';		break;
			case 414: $head = 'HTTP/1.0 414 Request URI too large';			break;
			case 415: $head = 'HTTP/1.0 415 Unsupported media type';		break;
			case 416: $head = 'HTTP/1.0 416 Request range not satisfiable'; break;
			case 417: $head = 'HTTP/1.0 417 Expectation failed';			break;
			case 422: $head = 'HTTP/1.0 422 Unprocessable entity';			break;
			case 423: $head = 'HTTP/1.0 423 Locked';						break;
			case 424: $head = 'HTTP/1.0 424 Failed dependency';				break;
			case 500: $head = 'HTTP/1.0 500 Internal server error';			break;
			case 501: $head = 'HTTP/1.0 501 Not Implemented';				break;
			case 502: $head = 'HTTP/1.0 502 Bad gateway';					break;
			case 503: $head = 'HTTP/1.0 503 Service unavailable';			break;
			case 504: $head = 'HTTP/1.0 504 Gateway timeout';				break;
			case 505: $head = 'HTTP/1.0 505 HTTP version not supported';	break;
			case 506: $head = 'HTTP/1.0 506 Variant also negotiates';		break;
			case 507: $head = 'HTTP/1.0 507 Insufficient storage';			break;
			case 510: $head = 'HTTP/1.0 510 Not extended';					break;
			default: return false;
			endswitch;
		}
		elseif(is_string($code)) return false; # add more header shortcuts here
		else return false;
		header($head);
		return true;
	}

	/**
	 * No Comments
	 * Strip comments from given source code.
	 *
	 * @note an open tag is added by default so the tokenizer works.
	 */
	public static function nocomments($str, $addopentag=true){
		$comment = array(T_COMMENT, T_DOC_COMMENT);
		$foundopentag = false;
		if ($addopentag) $str = '<'.'?'.$str;
		$tokens = token_get_all($str);
		$source = '';
		foreach($tokens as $token){
			if (is_array($token)){
				# if we added an open tag, ignore it.
				if ($addopentag && !$foundopentag && $token[0] === T_OPEN_TAG){
					$foundopentag = true;
					continue;
				}
				if (in_array($token[0], $comment)) continue;
				$token = $token[1];
			}
			$source .= $token;
		}
		return $source;
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
			$file = self::file($file, true, FILE_IGNORE_NEW_LINES);
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
