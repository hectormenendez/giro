<?php
/**
 * Application Common
 * Th construction of this classes is done manually in Core::app_load.
 */
class Application extends Library {

	public static $__vars = array();
	public static $__queue = array();
	private static $__destroyed = false;

	/**
	 * fallback to Library class or return null.
	 */
	public function __call($name, $args){
		if (method_exists('Library', $name))
			return call_user_func_array("Library::$name", $args);
		return null;
	}

	/**
	 * Destructor
	 * This will be run when the script finishes.
	 */
	 public static function _destruct(){
	 	# since this method will be inherited, make sure only run once.
	 	if (self::$__destroyed) return false;
	 	self::$__destroyed = true;
	 	foreach(self::$__queue as $queue){
	 		if (!is_array($queue) || empty($queue)) continue;
	 		$name = (string)array_shift($queue);
	 		if (!is_callable($name)) {
		 		notice("'$name' is not callable.");
		 		continue;
		 	}
	 		call_user_func_array($name,$queue);
	 	}
	 }


	 /**
	  * Run method after view.
	  */
	 public static function queue($queue = null){
	 	if (!is_array(self::$__queue)) self::$__queue = array();
	 	if (is_string($queue)) $queue = array($queue);
	 	elseif (empty($queue) || !is_array($queue)) return false;
	 	array_push(self::$__queue, $queue);
	 	return true;
	 }

	/**
	 * Method Getter
	 * Retrieve method declarations on given class.
	 */
	protected static function methods_get($class){
	 	$view = new ReflectionClass($class);
	 	$file = parent::file($view->getFileName());
	 	$methods = array();
	 	# get only methods that are not defined here.
	 	$m = array_diff(get_class_methods($class),get_class_methods(__CLASS__));
	 	foreach ($m as $methodname){
	 		$method = $view->getMethod($methodname);
	 		if ($method->isPrivate() || $method->isProtected()) continue;
	 		# ignore methods starting with underscore
	 		if ($methodname[0] == '_') continue;
	 		# obtain method's source [and cleanit a little]
	 		$src = array();
	 		for($i=$method->getStartLine()-1; $i < $method->getEndLine(); $i++){
	 			$line = preg_replace('%\s*(?:#|//).*%','', $file[$i]);
	 			$line = preg_replace('/\s+/',' ',trim($line));
	 			if (!empty($line)) $src[] = $line;
	 		}
	 		# remove visibility declarations
	 		$rx = '/(\s*(?:final|abstract|static|public|private|protected)\s)/i';
	 		$src[0] = preg_replace($rx,'', substr($src[0], 0, strpos($src[0], '{')+1));
	 		$methods[$methodname] = implode(' ', $src);
	 	}
	 	return $methods;
	 }

}