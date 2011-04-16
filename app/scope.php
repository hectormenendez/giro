<?php

class scopeControl extends Control {

	function scope(){

		$this->view->controlvar = "I'm declared from the controller";

		print_r($this->model);

	}

}