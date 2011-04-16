<?php
class scopeModel extends Model {

	public $modelvar = 23;

	public function scope(){ 

		$this->newmodelvar = "I am a model var : ". rand();
		$this->view->modelvar = "I ran from the model";

		
	}


}