<?php

interface iStorage {

  public function getDocument($loc); 

  public function removeDocument($loc);

  public function upsert($doc,$loc);
  
  public function insertDocument($doc,$loc);

  public function updateDocument($doc,$loc);

  public function getCount(); 

}

?>
