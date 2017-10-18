<?php

namespace mdml;
use Exception;
use stdClass;

class OAIingestor extends Ingestor {

	/**
	* OAIServiceURL
	*/
	public $OAIServiceURL;

	/**
	* metadataPrefix - the metadata prefix to use with OAI
	*/
	public $metadataPrefix = 'oai_dc';

	/**
	* sets - array of sets to ingest from OAI feed
	*/
	public $sets = array(); 

	/**
	* oai - the oai object to use for ingestion
	*/
	public $oai;

	/**
	* constructor
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
		$this->OAIServiceURL = $serviceArgs['OAIserviceURL'];
		try {
			$this->oai = new OAIParser($serviceArgs['OAIserviceURL']);
		} catch (Exception $e) {
			throw new ServiceException("Could not find OAI endpoint at: " . $serviceArgs['OAIserviceURL']);
		}
		$this->metadataPrefix = $serviceArgs['metadataPrefix'];
    }
    
    	public function run() {
		$this->ingest();
		return parent::run();
    	}

	/**
	* root_tags  - root tag that identifies an original metadata record
	*/
	public $root_tags = array(
				'oai_dc'=>'dc',
				'mods'=>'mods'
				);

	protected function processRecord($record) {
           $xml_string = \mdml\Utils::getCleanXML($record->saveXML());
           $doc = @simplexml_load_string($xml_string);
           $json = \mdml\Utils::Xml2Json($doc,'mdml:','mdml:_TXT');
           $fullRecord = json_decode($json);
		   if(property_exists($fullRecord,'GetRecord')) {
			$fullRecord = $fullRecord->GetRecord;
		   }
           $sourceID = $fullRecord->record->header->identifier;
		   $sourceURI = $this->OAIServiceURL."?verb=GetRecord";
		   $sourceURI .= "&metadataPrefix=".$this->metadataPrefix;
		   $sourceURI .= "&identifier=".$sourceID;
           if(!array_key_exists($this->metadataPrefix,$this->root_tags)) {
			   $errData = $this->getErrorData($sourceURI,$sourceURI);
               throw new RecordException("ERROR: no root_tag defined for metadata prefix: " . $this->metadataPrefix,$errData);
           }
           $root_tag = $this->root_tags[$this->metadataPrefix];
		   //handle deletes
		   if(!property_exists($fullRecord->record,'metadata')) {
			$header = $fullRecord->record->header;
			if($header->{'mdml:status'} == 'deleted') {
				$this->deleteRecord($sourceURI);
			}
			return TRUE;
		   }
		   if(!is_object($fullRecord->record->metadata)) return FALSE;
		   $original_record=NULL;
		   if(!property_exists($fullRecord->record->metadata,$root_tag)) {
			$root_tag = array_search($root_tag,$this->root_tags);
			if(!property_exists($fullRecord->record->metadata,$root_tag)) {
				if($root_tag == 'mods') {
					//root tag may be modsCollection
					if(property_exists($fullRecord->record->metadata,'modsCollection')) {
						$original_record = $fullRecord->record->metadata->modsCollection->mods;
					} else {
						$errData = $this->getErrorData($sourceURI,$sourceURI);
						throw new RecordException("Unknown root_tag: " . $root_tag, $errData );
					}
				} else {
					$errData = $this->getErrorData($sourceURI,$sourceURI);
					throw new RecordException("Unknown root_tag: " . $root_tag, $errData);
				}
			}
		   }
		   if(!$original_record) {
                      $original_record = $fullRecord->record->metadata->{$root_tag};
		   }
		   return $this->writeToTarget($original_record,$sourceURI);
	}

	protected function process($set=NULL) {
		 if($result = $this->oai->ListRecords($set,$this->metadataPrefix)) {
			$n=0;
		 	foreach($result->record as $record) {
				try {
					$this->processRecord($record);
				} catch(RecordException $re) {
					if($re->status != 1) {
						throw new ServiceException($re->getMessage());
					}
				}
				$n++;
		 	}
		 	if($result->resumptionToken) {
				if(strlen($result->resumptionToken)>0) {
					$this->process($set);
				} else {
					$this->oai->clearToken();
					return TRUE;
				}
		 	}
		 }
		 return TRUE;
	}

	public function ingestStatic($url,$metadata_prefix) {
		$contents = file_get_contents($url);
		$records = simplexml_load_string($contents);
		foreach($records as $record) {
			$this->processRecord($record,$metadata_prefix);		
		}
		return TRUE;
	}

	public function ingest() {
		if(count($this->sets)==0) {
             $this->process();
		} else {
		    foreach($this->sets as $set) {
               	$this->process($set);
        	}
		}
		return TRUE;
	}

}

