<?php namespace GM\Tests;

class TestCase extends \PHPUnit_Framework_TestCase {

    public function setUp() {
        \WP_Mock::setUp();
    }

    public function tearDown() {
        \WP_Mock::tearDown();
    }

}