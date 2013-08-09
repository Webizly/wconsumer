<?php
namespace Drupal\wconsumer\Rest\Authentication\Oauth;

use Drupal\wconsumer\Rest\Authentication as AuthencationBase;
use Drupal\wconsumer\Rest\Authentication\Credentials;
use Drupal\wconsumer\Common\AuthInterface;
use Drupal\wconsumer\Service;
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin as GuzzleOAuth;


/**
 * OAuth Authentication Class
 *
 * @package wconsumer
 * @subpackage request
 */
class Oauth extends AuthencationBase implements AuthInterface {

  /**
   * @var string
   */
  public $requestTokenURL;

  /**
   * @var string
   */
  public $authorizeURL;

  /**
   * @var string
   */
  public $accessTokenURL;

  /**
   * @var string
   */
  public $authenticateURL;



  public function sign_request(&$client)
  {
    $serviceCredentials = $this->_instance->getServiceCredentials();
    if (!isset($serviceCredentials)) {
      throw new \BadMethodCallException("Service credentials not set");
    }

    $userCredentials = $this->_instance->getCredentials();
    if (!isset($userCredentials)) {
      throw new \BadMethodCallException("No stored user credentials found");
    }

    /** @var $client Client */
    $client->addSubscriber(new GuzzleOAuth(array(
      'consumer_key'    => $serviceCredentials->token,
      'consumer_secret' => $serviceCredentials->secret,
      'token'           => $userCredentials->token,
      'token_secret'    => $userCredentials->secret,
    )));
  }

  public function authenticate(&$user)
  {
    $callback = $this->_instance->callback();

    $serviceCredentials = $this->_instance->getServiceCredentials();
    if (!$serviceCredentials) {
      throw new \BadMethodCallException("Service credentials should be set prior to calling authenticate()");
    }

    $client = Service::createHttpClient();
    $client->addSubscriber(new GuzzleOAuth(array(
      'consumer_key'    => $serviceCredentials->token,
      'consumer_secret' => $serviceCredentials->secret,
      'callback'        => $callback,
    )));

    $response = $client->post($this->requestTokenURL)->send()->getBody(true);

    $requestToken = static::parseParameters($response);

    $this->useRequestToken($requestToken);

    $authorizeUrl = $this->createAuthorizeURL($requestToken->token);
    drupal_goto($authorizeUrl, array('external' => TRUE));
  }

  public function logout(&$user) {
    $this->_instance->setCredentials(null, $user->uid);
  }

  public function onCallback(&$user, $values) {
    $serviceCredentials = $this->_instance->getServiceCredentials();
    if (!$serviceCredentials) {
      throw new \BadMethodCallException("Service credentials should be set prior to calling authenticate()");
    }

    $requestToken = $this->useRequestToken();

    $client = Service::createHttpClient();
    $client->addSubscriber(new GuzzleOAuth(array(
      'consumer_key'    => $serviceCredentials->token,
      'consumer_secret' => $serviceCredentials->secret,
      'token'           => $requestToken->token,
      'token_secret'    => $requestToken->secret,
      'verifier'        => @$values[0]['oauth_verifier'],
    )));

    $response = $client->post($this->accessTokenURL)->send()->getBody(true);

    $accessToken = static::parseParameters($response);
    $this->_instance->setCredentials($accessToken, $user->uid);
  }

  private function useRequestToken($value = null) {
    $key = "{$this->_instance->getName()}:oauth_request_token";

    if (func_num_args() > 0) {
      $_SESSION[$key] = $value;
    }
    else {
      if (!isset($_SESSION[$key])) {
        throw new \BadMethodCallException('Request token data not found in current session');
      }
    }

    return $_SESSION[$key];
  }

  private function createAuthorizeURL($token) {
    $delimiter = '?';
    if ((string)parse_url($this->authorizeURL, PHP_URL_QUERY) !== '') {
      $delimiter = '&';
    }

    $url = $this->authorizeURL . $delimiter . 'oauth_token='.urlencode($token);

    return $url;
  }

  // This function takes a input like a=b&a=c&d=e and returns the parsed
  // parameters like this
  // array('a' => array('b','c'), 'd' => 'e')
  private static function parseParameters($input) {
    if (!isset($input) || !$input) {
      return array();
    }

    $pairs = explode('&', $input);
    $parsed_parameters = array();

    foreach ($pairs as $pair) {
      $split = explode('=', $pair, 2);
      $parameter = urldecode($split[0]);
      $value = isset($split[1]) ? urldecode($split[1]) : '';

      if (isset($parsed_parameters[$parameter])) {

        // We have already recieved parameter(s) with this name, so add to the list
        // of parameters with this name
        if (is_scalar($parsed_parameters[$parameter])) {
          // This is the first duplicate, so transform scalar (string) into an array
          // so we can add the duplicates
          $parsed_parameters[$parameter] = array($parsed_parameters[$parameter]);
        }

        $parsed_parameters[$parameter][] = $value;
      }
      else {
        $parsed_parameters[$parameter] = $value;
      }
    }

    if (empty($parsed_parameters['oauth_token']) || empty($parsed_parameters['oauth_token_secret'])) {
      throw new OAuthException("Failed to parse Access Token response '{$input}'");
    }

    return new Credentials($parsed_parameters['oauth_token'], $parsed_parameters['oauth_token_secret']);
  }
}