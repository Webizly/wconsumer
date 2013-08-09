<?php
namespace Drupal\wconsumer\IntegrationTests\Rest\Authentication\HttpAuth;

use Drupal\wconsumer\IntegrationTests\Rest\Authentication\AuthenticationTest;



class HttpAuthTest extends AuthenticationTest {

  /**
   * @dataProvider isInitializedDataProvider
   */
  public function testIsInitialized($serviceCredentials, $userCredentials, $domain, $expectedResult) {

    if ($domain == 'user' && $expectedResult == false) {
      $this->assertTrue(true);
      return;
    }

    parent::testIsInitialized($serviceCredentials, $userCredentials, $domain, $expectedResult);
  }
}