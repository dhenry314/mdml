<?php
namespace mdml;
use stdclass;

class mdmlDoc {
  

  function __construct($payload,$sourceURI,$originURI=NULL) {
        $this->{'@context'} = new stdclass();
        $this->{'@context'}->mdml = "http://data.mohistory.org/ns/mdml/";
        $this->{'mdml:payload'} = $payload;
        $this->{'mdml:sourceURI'} = $sourceURI;
        if(!$originURI) {
                $originURI = $sourceURI;
        }
        $this->{'mdml:originURI'} = $originURI;
  }


}

?>

