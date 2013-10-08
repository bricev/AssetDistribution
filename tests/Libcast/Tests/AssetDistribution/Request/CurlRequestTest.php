<?php
namespace Libcast\Tests\AssetDistribution\Request;

use Libcast\AssetDistribution\Request\CurlRequest;

class CurlRequestTest extends \PHPUnit_Framework_TestCase
{
    protected $conn;

    public function testMethods()
    {
        // System (in)sanity check.
        $this->assertEquals(1, 1);
    }
}
