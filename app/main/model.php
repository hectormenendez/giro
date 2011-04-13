<?php
class MainModel extends Model {
	
	public $data = 'Model data';
	
	public function __construct(){
		echo "Model instanced.\n";
	}
	
}