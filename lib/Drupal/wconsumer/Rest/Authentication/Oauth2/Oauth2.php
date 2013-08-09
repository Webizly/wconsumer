<?php
namespace Drupal\wconsumer\Rest\Authentication\Oauth2;

use Drupal\wconsumer\Common\AuthInterface;
use Drupal\wconsumer\Exception as WconsumerException;
use Drupal\wconsumer\Service;
use Drupal\wconsumer\Rest\Authentication as AuthencationBase;
use Drupal\wconsumer\Rest\Authentication\Credentials;
use Drupal\wconsumer\Rest\Authentication\Oauth2\Plugin as Oauth2Plugin;
use Guzzle\Http\Client;

/**
 * OAuth2 Authentication Class
 *
 * @package wconsumer
 * @subpackage request
 */
class Oauth2 extends AuthencationBase implements AuthInterface {
  /**
   * @var string
   */
  public $authorizeURL;

  /**
   * @var string
   */
  public $accessTokenURL;

  /**
   * Scopes to be requested access to
   *
   * @var array
   */
  public $scopes = array();

  /**
   * Guzzle client to make HTTP requests to oauth provider
   *
   * @var \Guzzle\Http\Client
   */
  public $client;



  public function sign_request($client)
  {
    $accessToken = $this->_instance->getCredentials()->secret;

    /** @var $client Client */
    $client->addSubscriber(new Oauth2Plugin($accessToken));
  }

  /**
   * Authenticate the user and set them up for OAuth Authentication
   *
   * @param object $user The user object
   */
  public function authenticate($user)
  {
    // Retrieve the OAuth request token
    $callback = $this->_instance->callback();
    $serviceCredentials = $this->_instance->getServiceCredentials();

    $url =
      $this->authorizeURL .
      http_build_query(array(
        'client_id'     => $serviceCredentials->token,
        'redirect_uri'  => $callback,
        'scope'         => implode(',', $this->scopes),
        'state'         => 'wconsumer',
      ), null, '&');

    drupal_goto($url, array('external' => TRUE));
  }

  /**
   * Log the User out of the System
   *
   * @uses ServiceBase Removes their credentials
   */
  public function logout($user) {
    $this->_instance->setCredentials(null, $user->uid);
  }

  /**
   * Callback for authencation
   *
   * @param object $user The User Object
   * @param array $values The array of values passed
   *
   * @throws WconsumerException
   */
  public function onCallback(&$user, $values) {
    // Check the state
    if (!isset($values[0]['state']) || $values[0]['state'] !== 'wconsumer') {
      throw new WconsumerException('State for OAuth2 Interface not matching');
    }

    if (empty($values[0]['code'])) {
      throw new WconsumerException('No code passed to OAuth2 Interface');
    }

    $serviceCredentials = $this->_instance->getServiceCredentials();

    // @codeCoverageIgnoreStart
    if (!isset($this->client)) {
      $this->client = Service::createHttpClient();
    }
    // @codeCoverageIgnoreEnd

    $request = $this->client->post(
      $this->accessTokenURL,
      array(
        'Accept' => 'application/json'
      ),
      array(
        'client_id'     => $serviceCredentials->token,
        'client_secret' => $serviceCredentials->secret,
        'code'          => $values[0]['code'],
      )
    );

    $response = $request->send();
    if ($response->isError()) {
      throw new WconsumerException('Unknown error on OAuth 2 callback: '.print_r($response, true));
    }

    $response = $response->json();
    if (!empty($response['error'])) {
      throw new WconsumerException("Error while requesting access_token: '{$response['error']}'");
    }

    if (empty($response['access_token'])) {
      throw new WconsumerException("Invalid access token response: '".var_export($response, true)."'");
    }

    $credentials = new Credentials('dummy', $response['access_token']);

    $this->_instance->setCredentials($credentials, $user->uid);
  }
}
