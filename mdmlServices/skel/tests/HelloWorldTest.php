<?php
namespace tests;

class HelloWorldTest extends \PHPUnit_Framework_TestCase
{
    public function testCall()
    {
	$config = include __DIR__ . '/../config.php';
	$url = "http://localhost".$config['BASE_PATH']."serviceCall";

        $client = new \mdml\ServiceClient($config['auth']);
	$request = '{
    		"type": "jsonwsp/request",
    		"version": "1.0",
    		"methodname": "HelloWorld",
    		"args": {
    			"yourName":"George"
    		},
    		"mirror": {
        		"id": 1
    		}
	}';
        $result = $client->post($request,$url);
        $this->assertTrue(property_exists($result,'methodname'));
    }    
}

?>
