<?php
/**
 * Instance Wrapper
 * Allows Libraries to present themselves as non-static, allowing multiple 
 * instances of themselves.
 * 
 * @version v1.0 [29/SEP/2010]
 * @author Hector Menendez
**/
abstract class Instance extends Library {
	
	private $__object = null;
	private $__parent = null;

	/**
	 * Make sure everything needed is set, and returns itself by reference.
	**/
	public function &__construct(&$object = false){
		$name = $this->__parent_name();
		if (!is_object($object) && !is_resource($object))
			error("An object or resource must be provided as an instance of $name");
		$this->__object = &$object;
		$this->__parent = new ReflectionClass($name);
		return $this;
	}

	/**
	 * Call Redirector
	 * Forwards all calls to their static counterparts. 
	 * [appending the database object as argument]
	 *
	 * @param [string] $name 
	 * @param [string] $args 
	 *
	 * @return void
	**/
	final public function __call($name,$args){
		if (!is_callable("{$this->__parent->name}::$name"))
			error("invalid method: '$name'");
		return call_user_func_array(
			"{$this->__parent->name}::$name",$this->__append_param($name,$args)
		);
	}

	/**
	 * Instance Parent Finder
	 * Finds out (th hard way) tha name of the class that's really calling.
	 * The double slashes are there to avoid naming conflicts.
	 *
	 * @return string	The parent class name.
	 */
	private function __parent_name(){
		$dbt = debug_backtrace();
		if (!isset($dbt[2]['class']))
			error(__CLASS__.' was not called from a valid class.');
		return $dbt[2]['class'];
	}

	/**
	 * Parameter Appender
	 * Append main object to the end of parameters in given method.
	 *
	 * @param string $name method name.
	 * @param array $args  argyuments array
	 * @return array arrays with appendend object-
	 * @author Hector Menendez
	 */
	private function __append_param($name,$args){
		# Get method's declared parameters.
		try {
			$params = $this->__parent->getMethod($name)->getParameters();
		} catch(ReflectionException $e){
			if (Core::config('error')) die('Instance :: '.$e->getMessage());
			else die('Instance Error');
		}
		# if an argument is not set, set its default. [unless name begins with '__']
		# i know the one-liner is a pain, but this way getName only gets called when needed.
		$c = count($params);
		for($i=0; $i<$c; $i++)
			if (!isset($args[$i]) && strlen($name=$params[$i]->getName())>2 && substr($name,0,2) !='__')
				# http://php.net/manual/en/reflectionparameter.getdefaultvalue.php
				# Note:
				# Due to implementation details, it is not possible to get the default 
				# value of built-in functions or methods of built-in classes. Trying to 
				# do this will result a ReflectionException being thrown.
				# 
				# hence this:
				try {
					$args[$i] = $params[$i]->getDefaultValue();	
				} catch (Exception $e) { $args[$i] = null; }
		# Appends object.
		$args[] = $this->__object;
		return $args;
	}

}