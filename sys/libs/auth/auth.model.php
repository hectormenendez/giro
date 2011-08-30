<?php
/**
 * Data handling methods.
 *
 * @working 2011/AUG/27 23:57
 * @created 2011/AUG/26 08:39
 */
final class Auth_Model extends Library {

	private $db    = null;

	public $logged = null;

	/**
	 * Checks if user already logged in.
	 *
	 * @working 2011/AUG/27 23:54
	 * @created 2011/AUG/26 08:40
	 */
	public function __construct(&$db){
		if (strtolower((string)parent::class_calling()) != 'auth')
			error('Direct Instancing is disabled.');
		$this->db = &$db;
		# is this UUID logged in? 
		$auth = $db->select('auth_login', 'id_user,logged', 'uuid=? LIMIT 1',UUID);
		# set logged status to:
		# id_user, logged in.
		#   false, not logged in.
		#    null, failed login.
		$this->logged = empty($auth)? false : ( (bool)$auth['logged']? (int)$auth['id_user'] : null );
	}

	/**
	 * Check if user is valid.
	 *
	 * @return mixed  Same schema as $this->logged:
	 *                - int   user id when loggen in succesfuly.
	 *                - false when user is not logged in.
	 *                - null  when user failed a login attempt.
	 *
	 * @working 2011/AUG/27 23:51
	 * @created 2011/AUG/26 08:45
	 */
	public function login(){
		# user already logged? no need of doing this.
		if ($this->logged) return $this->logged;
		# if no post data is being sent; do nothing.
		if (empty($_POST)) return false;
		# post is sent, make sure data is valid.
		if (count($_POST) != 2     ||
			!isset($_POST['user']) || empty($_POST['user']) ||
			!isset($_POST['pass']) || empty($_POST['pass'])
		) return $this->fail();
		# allow only A-Za-z0-9_
		$user = preg_replace("%[^a-zA-z0-9_]|['\"\`\\\]%", '', $_POST['user']);
		$pass = sha1($_POST['pass']);
		# matching info?
		$auth = $this->db->select('auth_users','*','user=? AND pass=? LIMIT 1',$user,$pass);
		if (empty($auth)) return $this->fail();
		return $this->success($auth['id']);
	}

	/**
	 * Kills a UUID session.
	 *
	 * @working 2011/AUG/27 23:55
	 * @created 2011/AUG/26 08:48
	 */
	public function logout(){
		$this->db->delete('auth_login','uuid=?',UUID);
	}

	/**
	 * Write on database when a user fails to login.
	 *
	 * @working 2011/AUG/27 23:56
	 * @created 2011/AUG/27 23:23
	 */
	private function fail(){
		# insert or replace row.
		$this->db->insert('auth_login', array(
			'uuid'    => UUID,
			'id_user' => null,
			'logged'  => 0,
			'date'    => date(DATE_W3C)
		),  'id');
		return $this->logged = null;
	}

	/**
	 * Write on database when a user logged in successfully.
	 *
	 * @working 2011/AUG/27 23:57
	 * @created 2011/AUG/27 23:32
	 */
	private function success($id){
		$id = (int)$id;
		# insert or replace row.
		$this->db->insert('auth_login',array(
			'uuid'    => UUID,
			'id_user' => $id,
			'logged'  => 1,
			'date'    => date(DATE_W3C)
		),  'id');
		return $this->logged = $id;
	}

}