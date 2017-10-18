<?php

class HelloWorld extends \mdml\Service {

  function __construct($serviceArgs,$request,$response,$allowablePaths) {
      parent::__construct($serviceArgs,$request,$response,$allowablePaths);
  }

  public function run() {
	$this->response = array("content"=>"Hello ".$this->serviceArgs['yourName']."!","dateResponded"=>date('c'));
	return parent::run();
  }

  
}

?>
