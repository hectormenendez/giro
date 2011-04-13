<?php

class MainControl extends Control {
	
	public function main(){
		echo "This is mainControl\n";
		if (isset($this->model) && isset($this->model->data)) echo $this->model->data,"\n";
	}
	
}