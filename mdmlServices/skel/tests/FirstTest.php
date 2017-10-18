<?php
namespace tests;
use PHPUnit\Framework\TestCase;
 
class FirstTest extends TestCase
{
    	public function testTrueCheck()
	{
    		$condition = true;
    		$this->assertTrue($condition);
	}
}
?>
