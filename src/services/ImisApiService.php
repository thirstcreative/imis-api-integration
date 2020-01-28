<?php
/**
 * IMIS API Integration plugin for Craft CMS 3.x
 *
 * integrates the IMIS API with CraftCMS
 *
 * @link      https://www.thirstcreative.com.au
 * @copyright Copyright (c) 2019 Thirst Creative
 */

namespace thirstcreative\imisapiintegration\services;

use GuzzleHttp\Exception\RequestException;
use thirstcreative\imisapiintegration\ImisApiIntegration;
use GuzzleHttp\Client as GuzzleHttpClient;

use Craft;
use craft\base\Component;
use craft\helpers\UrlHelper;
use craft\services\Plugins;

/**
 * ImisApiService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Daniel Gonzalez Adarve
 * @package   ImisApiIntegration
 * @since     0.0.1
 */
class ImisApiService extends Component
{

  public $guzzleClient;

  // Public Methods
  // =========================================================================
  public function __construct($config = [])
  {
    parent::__construct($config);
    $this->guzzleClient = new GuzzleHttpClient();
  }

  public function requestImisBearerToken()
  {

    try {
      $response = $this->guzzleClient->request(
        'POST',
        rtrim(ImisApiIntegration::$plugin->getSettings()->imisURL, '/').'/token',
        [
          'form_params' => [
            'Username'    => ImisApiIntegration::$plugin->getSettings()->imisUsername,
            'Password'    => ImisApiIntegration::$plugin->getSettings()->imisPassword,
            'Grant_type'  => 'password',
          ]
        ]
      );

      if ($response->getStatusCode() === 200) {
        $body = $response->getBody();
        $response = $this->parseJson($body->getContents());
        return $response['access_token'] ?? false;
      }
    } catch (RequestException $e) {
      echo 'Caught exception: ',  print_r(json_decode($e->getMessage()),true), "\n";
    }

  }

  public function parseJson($json)
  {

    $jsonArray = array();
    $jsonArray = json_decode($json, true);

    if (json_last_error() === JSON_ERROR_NONE) {
      return $jsonArray;
    }

    return false;

  }

}
