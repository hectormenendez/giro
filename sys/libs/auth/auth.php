<?php
/**
 * Provides an automatized way of handling user authentication.
 *
 * @created 2011/AUG/25 17:48
 */
abstract class Auth  extends Application_Common {

	private static $model = null;
	private static $view  = null;

	/**
	 * Loads everything at once.
	 *
	 * @created 2011/AUG/29 14:06
	 */
	final public static function control(&$app=false){
		if (!parent::is_control($app))
			error('Argument must contain a Control instance.');
		self::model($app->model);
		self::view($app->view);
	}

	/**
	 * Auth Model driver load.
	 *
	 * @log     2011/AUG/29 21:00 Instancing now uses Core::library's autoload.
	 * @working 2011/AUG/27 23:55
	 * @created 2011/AUG/25 17:49
	 */
	final public static function model(&$app=false){
		# instanced from a model?
		if (!parent::is_model($app))
			error('Argument must contain a Model instance.');
		# check for tokens
		if (!defined('TOKEN_SECRET') || !defined('TOKEN_PUBLIC'))
			error('Model Tokens are required');
		# has valid database?
		if (!is_object($db = Model::db_look($app)))
			error('A database must be instantiated before loading this.');
		if ($db->driver !='mysql')
			error('Support for your driver is not yet implemented');
		# does an auth table exists on database?
		if (!$db->is_table('auth_users') || !$db->is_table('auth_login')){
			if (!file_exists($path = strtolower(AUTH.__CLASS__.'.'.$db->driver.'.sql')))
				error('Could not find Database schema.');
			# import default tables
			if (!$db->import($path)) error('Import failed.');
			# create admin user
			if (!is_string($user = self::config('admin_user'))) $user = 'admin';
			if (!is_string($pass = self::config('admin_pass'))) $pass = 'admin';
			$db->insert('auth_users', array(
				'user' => $user,
				'pass' => sha1($pass),
				'date' => date(DATE_W3C)
			)) || error('Could not create admin.');
		}
		# instantiate and set,
		self::$model = new Auth_Model($db);
		$app->auth = &self::$model;
		return true;
	}

	/**
	 * Auth View driver load.
	 *
	 * @log     2011/AUG/29 21:00 Instancing now uses Core::library's autoload.
	 * @working 2011/AUG/27 23:53
	 * @created 2011/AUG/27 01:59
	 */
	final public static function view(&$app=false){
		# instanced from a view? 
		if(!parent::is_view($app))
			error('Argument must contain a View instance.');
		# make sure model has been instanced
		if (!self::$model instanceof Auth_Model)
			error('Auth Model was not detected.');
		# instantiate and set
		self::$view = new Auth_View(self::$model);
		$app->auth = &self::$view;
		return true;
	}

}