<?php
/**
 * IMIS API Integration plugin for Craft CMS 3.x
 *
 * integrates the IMIS API with CraftCMS
 *
 * @link      https://www.thirstcreative.com.au
 * @copyright Copyright (c) 2019 Thirst Creative
 */

namespace thirstcreative\imisapiintegration\variables;

use thirstcreative\imisapiintegration\ImisApiIntegration;

use Craft;

class ImisApiVariable
{

  // Public Methods
  // =========================================================================

  public function get($type, $limit = 10)
  {
    return ImisApiIntegration::$plugin->imisApiService->connect($type, $limit);
  }

}
