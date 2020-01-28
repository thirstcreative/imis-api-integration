<?php
/**
 * IMIS API Integration plugin for Craft CMS 3.x
 *
 * integrates the IMIS API with CraftCMS
 *
 * @link      https://www.thirstcreative.com.au
 * @copyright Copyright (c) 2019 Thirst Creative
 */

namespace thirstcreative\imisapiintegration\models;

use thirstcreative\imisapiintegration\ImisApiIntegration;

use Craft;
use craft\base\Model;

/**
 * ImisApiIntegration Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Daniel Gonzalez Adarve
 * @package   ImisApiIntegration
 * @since     0.0.1
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================
    public $imisURL;
    public $imisUsername;
    public $imisPassword;
    public $bearerAccessToken;
    public $bearerAccessTokenIssued;
    public $bearerAccessTokenExpires;

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
          [['imisURL','imisUsername', 'imisPassword'], 'required']
        ];
    }
}
