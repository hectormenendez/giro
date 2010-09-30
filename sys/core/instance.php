<?php
/**
 * Allows Libraries to present themselves as non-static, therefore allowing multiple instances of themselves.
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
	public function &__construct(&$object=false){
		$name = $this->__parent_name();
		if (!is_object($object)) self::error("An object must be provided in $name");
		$this->__object = &$object;
		$this->__parent = new ReflectionClass($name);
		return $this;
	}

	/**
	 * Forwards all calls to their static counterparts. [appending the database object as argument]
	 *
	 * @param string $name 
	 * @param string $args 
	 * @return void
	 * @author Hector Menendez
	**/
	final public function __call($name,$args){
		if (!is_callable("{$this->__parent->name}::$name")) 
			call_user_func("{$this->__parent->name}::error","invalid_method: '$name'");
		return call_user_func_array("{$this->__parent->name}::$name",$this->__append_param($name,$args));
	}

	/**
	 * Returns the name of the call calling this.
	 *
	 * @return string	Clas name.
	 * @author Hector Menendez
	 */
	private function __parent_name(){
		$dbt = debug_backtrace();
		if (!isset($dbt[2]['class'])) self::error(__CLASS__.' was not called from a valid class.');
		return $dbt[2]['class'];
	}

	/**
	 * Append main object to the end of parameters in given method.
	 *
	 * @param string $name method name.
	 * @param array $args  argyuments array
	 * @return array arrays with appendend object-
	 * @author Hector Menendez
	 */
	private function __append_param($name,$args){
		# Get method's declared parameters.
		$params = $this->__parent->getMethod($name)->getParameters();
		# if an argument is not set, set its default. [unless name begins with '__']
		# i know the one-liner is a pain, but this way getName only gets called when needed.
		$c = count($params);
		for($i=0; $i<$c; $i++)
			if (!isset($args[$i]) && strlen($name=$params[$i]->getName())>2 && substr($name,0,2) !='__')
				$args[$i] = $params[$i]->getDefaultValue();
		# Appends object.
		$args[] = $this->__object;
		return $args;
	}

}