<?php
namespace Drupal\wconsumer\Tests\Service;

use Drupal\wconsumer\Service\Base;



class BaseTest extends \PHPUnit_Framework_TestCase {

  public function testServiceName() {
    $object = new FooService();
    $this->assertEquals('drupal__wconsumer__tests__service__fooservice', $object->getName());
  }

  public function testSpecificServiceName() {
    $object = new FooServiceWithName();
    $this->assertEquals('specialservice', $object->getName());
  }

  public function testCallbackUrl() {
    $service = new FooService();
    $this->assertNotEmpty($service->callback());
  }
}

/**
 * @ignore
 */
class FooService extends Base {
}

/**
 * @ignore
 */
class FooServiceWithName extends Base {
  protected $name = 'specialservice';
}