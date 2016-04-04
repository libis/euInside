<?php

/**
 * User: NaeemM
 * Date: 1/04/2016
 */

require '../vendor/autoload.php';
class dmtTests extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->client = new GuzzleHttp\Client([
            'base_uri' => 'http://mybookstore.com'
        ]);
    }
    public function testCanBeNegated()
    {
        // Assert
        $this->assertEquals(-1, 0);
    }
}