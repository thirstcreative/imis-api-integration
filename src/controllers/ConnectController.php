<?php
/**
 * IMIS API Integration plugin for Craft CMS 3.x
 *
 * Description
 *
 * @link      https://www.thirstcreative.com.au
 * @copyright Copyright (c) 2019 Daniel Gonzalez Adarve
 */

namespace thirstcreative\imisapiintegration\controllers;

use thirstcreative\imisapiintegration\ImisApiIntegration;
use thirstcreative\imisapiintegration\services\ImisApiService;

use Craft;
use craft\web\Controller;

class ConnectController extends Controller
{
  protected $allowAnonymous = true;

  public function actionIndex()
  {

    $redirect = $this->redirect('settings/plugins/imis-api-integration');
    $imisApiService = new ImisApiService();

    if (!$imisApiService->connect())
    {
      Craft::$app->getSession()->setError(\Craft::t('app', 'Couldn’t connect to iMIS.'));
      return $redirect;
    }

    return $redirect;

  }

}
