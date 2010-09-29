<?php
/**
 * Application manager
 *
 * @version v2.0	[29/SEP/2010]
 * @author Hector Menendez
 *
 * @log		v2.0	[29/SEP/2010]		Rewritten the load method. It now handles the file loading as well.
 * @log		v1.0r2	[27/SEP/2010]		Added $ctrl, argument to override the control being called.
 * @log		v1.0	------------		Basic functionality.
 */
abstract class Application extends Library {

	private static $loaded= array();
	
	public static function load($name=false, $ctrl=false, $error=_error){
		# First determine if the user didn't send  a controller overrider. example:
		# main::override = APP/main/override.php
		$ctrl = false;
		if (($pos = strpos($name=strtolower($name),'::'))!==false){
			$ctrl = substr($name,$pos+2);
			$name = substr($name,0,$pos);
		}
		$model = false;
		$found = false;
		# Give priority to files over directories. meaning: APP/main.php will override APP/main/main.php
		if (file_exists($file=APP.$name.EXT)) $found = true;
		elseif (file_exists($file=APP.$name.SLASH.$name.EXT)){
			$found = true;
			# if an controller overrider exists, include it instead of default one.
			if ($ctrl!==false && file_exists($file=APP.$name.SLASH.$ctrl.EXT)) 
				$controller = $name.$ctrl.'control';
			else $controller = $name.'control';
			// Check if there's a Model available. and if it hasn't been loaded yet.
			if (file_exists($model=APP.$name.SLASH.'model'.EXT)){
				if (!in_array($model, self::$loaded)) include $model;
				self::$loaded[] = $model;
				$model = $name.'Model';
				if (class_exists($model,false)) $model = new $model;
			} else $model = false;
		}
		# if no file was found, return an error.
		if (!$found) return self::error('control_invalid',false,$error);
		# Avoid loading the same controller more than once.
		if (in_array($file,self::$loaded)) return self::error('control_loaded',false,$error);
		include $file;
		self::$loaded[] = $file;
		# Make sure the class exists before trying to do anything.
		if (!class_exists($controller,false)) return self::error('control_name',false,$error);
		# Instantiate and run init method.
		$controller =  new $controller($model);
		$ctrl = $ctrl?$ctrl:$name;
		$controller->$ctrl();
		return true;
	}

}

class Model extends Application {}

class Control extends Application {

	public $model = null;

	/**
	 * Makes the model available for the user.
	 * In theory, the user won't need to use a constructor, since I provide an auto-called method
	 * but I didn't declare this final so the user can override this funcionality..
	 *
	 * @param string $model 
	 * @author Hector Menendez
	 */
	public function __construct(&$model){ $this->model = &$model; }

}
