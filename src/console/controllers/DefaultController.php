<?php
/**
 * imis-api-integration plugin for Craft CMS 3.x
 *
 * integrates the IMIS API with CraftCMS
 *
 * @link      https://www.thirstcreative.com.au
 * @copyright Copyright (c) 2019 Daniel Gonzalez Adarve
 */

namespace thirstcreative\imisapiintegration\console\controllers;

use thirstcreative\imisapiintegration\ImisApiIntegration;

use Craft;
use Tightenco\Collect\Support\Collection;
use yii\console\Controller;
use yii\helpers\Console;
use thirstcreative\imisapiintegration\services\ImisApiService;

use craft\elements\Entry;
use craft\elements\MatrixBlock;
use DateTime;

use GuzzleHttp\Exception\RequestException;

/**
 * Default Command
 *
 * The first line of this class docblock is displayed as the description
 * of the Console Command in ./craft help
 *
 * Craft can be invoked via commandline console by using the `./craft` command
 * from the project root.
 *
 * Console Commands are just controllers that are invoked to handle console
 * actions. The segment routing is plugin-name/controller-name/action-name
 *
 * The actionIndex() method is what is executed if no sub-commands are supplied, e.g.:
 *
 * ./craft imis-api-integration/default
 *
 * Actions must be in 'kebab-case' so actionDoSomething() maps to 'do-something',
 * and would be invoked via:
 *
 * ./craft imis-api-integration/default/do-something
 *
 * @author    Daniel Gonzalez Adarve
 * @package   Imisapiintegration
 * @since     0.0.1
 */
class DefaultController extends Controller
{
  public $imisURL;
  public $bearerAccessToken;
  public $guzzleClient;
  public $servicesArray = ['Systems_Supplier','Product_Supplier','Infrastructure_Provider','Consultant_Advisory','Professional_Services','Research_Education','Government','Not_For_Profit'];
  public $expertiseArray = ['New_Mobility_Solutions','Passenger_Trans_Operator','Smart_Infrastructure','Future_Freight','Automated_Vehicles','Analytics','Policy_Regulation'];

  public $apiService;

  public function __construct($id, $module, $config = [])
  {
    parent::__construct($id, $module, $config);

    $this->imisURL = ImisApiIntegration::$plugin->getSettings()->imisURL;
    // Start the API Service
    $this->apiService = new ImisApiService;
    $this->bearerAccessToken = $this->apiService->requestImisBearerToken();

    $this->guzzleClient = $this->apiService->guzzleClient;
  }

  /**
   * Fetches member information from iMIS
   *
   * The first line of this method docblock is displayed as the description
   * of the Console Command in ./craft help
   *
   * @return mixed
   */
  public function actionSync()
  {
    $this->getOrganizations();

    //return true;
  }

  public function getOrganizations()
  {

    $fetch = TRUE;
    $offset = 0;
    $organizations = collect();
    $sectionType = "members";
    // Get all current member entries
    $members = collect(Entry::find()->section($sectionType)->all());

    // Fetch all data from iMIS
    while ($fetch) {

      $params = [
        'limit' => 500,
        'offset' => $offset,
        'Status' => 'A',
        'CustomerTypeCode' => 'in:SIL|SME|PLA|GOL|STA'
      ];
      $response = $this->makeGuzzleRequest('GET', '/api/Party', $params, false);

      // Check for next offset
      if ($response['HasNext']) {
        $offset = $response['NextOffset'];
      } else {
        $fetch = FALSE;

      }

      if(!is_array($response['Items'])) $fetch = FALSE;

      $filteredOrganizations = collect(array_values($response['Items'])[1])->map(function ($item, $key) use ($sectionType, $members) {
        $memberId = $item['Id'];
        $OrganizationName = $item['OrganizationName'];

        // Init Values
        $customFields = [
          'memberId'          => $memberId,
          'membershipType'    => null,
          'address'           => null,
          'bio'               => null,
          'contactEmail'      => null,
          'contactPerson'     => null,
          'contactPhone'      => null,
          'expertiseAreas'    => [],
          'services'          => [],
          'socialNetworks'    => null,
          'website'           => null,
        ];

        $ITSA_Directory = $this->makeGuzzleRequest('GET','/api/ITSA_Directory/'.$memberId, [], false);
        if($ITSA_Directory !== FALSE && count($ITSA_Directory['Properties']['$values'])) {

          $ITSA_Directory_values = collect($ITSA_Directory['Properties']['$values']);

          $ITSA_Directory_values->map(function($dv, $dk) use ($memberId, &$customFields) {
            // Check for services
            if(isset($dv['Name']) && in_array($dv['Name'], $this->servicesArray)){
              if($dv['Value']['$value']){
                array_push($customFields['services'], $this->toCamelCase($dv['Name']));
              }
            }

            // Check for areas of expertise
            if(isset($dv['Name']) && in_array($dv['Name'], $this->expertiseArray)){
              if($dv['Value']['$value']){
                array_push($customFields['expertiseAreas'], $this->toCamelCase($dv['Name']));
              }
            }

          });

          if(!count($customFields['services'])){ $customFields['services'] = null; }
          if(!count($customFields['expertiseAreas'])){ $customFields['expertiseAreas'] = null; }

          $customFields['contactPerson'] = $this->getCompanyAndContactInformation($ITSA_Directory_values ,'Contact_Person');
          $customFields['contactPhone'] = $this->getCompanyAndContactInformation($ITSA_Directory_values ,'Contact_Phone');
          $customFields['contactEmail'] = $this->getCompanyAndContactInformation($ITSA_Directory_values ,'Contact_Email');
        }

        // Website
        $customFields['website'] = $item['WebsiteUrl'] ?? null;

        // CustomerTypeDescription (Membership Type)
        $customFields['membershipType'] = $this->getOrganizationDataObject($item['AdditionalAttributes']['$values'], "Name", "CustomerTypeDescription", "value") ?? null;

        // Get Addresses
        $Addresses = $this->getOrganizationDataObject($item['Addresses']['$values'], "AddressPurpose", "Business" );
        if(!is_null($Addresses)){
          $customFields['address'] = $this->getAddresses($Addresses);
        }

        // Get Social Networks
        $SocialNetworks = $this->getOrganizationDataObject($item['SocialNetworks']['$values'], false, false);
        if(!is_null($SocialNetworks)){
          $SocialNetworks = $this->getSocialNetworks($SocialNetworks);
          $customFields['socialNetworks'] = count($SocialNetworks) > 0?$SocialNetworks:null;
        }

        $logoResponse = $this->makeGuzzleRequest('GET', '/api/PartyImage?limit=1&PartyId=' .$memberId, [], false);

        // check if image exists and contains data
        if($logoResponse !== FALSE && count($logoResponse['Items']['$values']) && isset($logoResponse['Items']['$values'][0]['Image']['$value'])){

          // get mime type from base64 blurb
          $base64image = $logoResponse['Items']['$values'][0]['Image']['$value'];
          $imgdata = base64_decode($base64image);
          $f = finfo_open();
          $mimeType = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);

          $customFields['logo'] = [
            'filename' => [
              strtolower(str_replace(['.',',','-'],'_', $OrganizationName)).'.'.$this->mime2ext($mimeType)
            ],
            'data' => [
              'data:'.$mimeType.';base64,'.$base64image
            ]
          ];
        }
         //Get Bio
        $bioResponse = $this->makeGuzzleRequest('GET', '/api/ITSACustom/' .$memberId, [], false);

        if($bioResponse !== FALSE && count($bioResponse['Properties']['$values'])) {
          $customFields['bio'] = $this->getOrganizationDataObject($bioResponse['Properties']['$values'], "Name", "Profile_Org", "value");
        }

        // Upsert Entries
        $filtered = $members->filter(function ($value, $key) use ($memberId) {
          return $value->memberId === $memberId;
        })->first();

        if(!is_null($filtered)){
          // Update Entry
          $entry = $filtered;
          $entry->title = $OrganizationName;
        }else{
          // Insert Entry
          // Figure out the section & entry type
          $section = Craft::$app->sections->getSectionByHandle($sectionType);
          $entryTypes = $section->getEntryTypes();
          $entryType = reset($entryTypes);

          // Create an entry Object
          $entry = new Entry([
            'sectionId'     => $section->id,
            'typeId'        => $entryType->id,
            'fieldLayoutId' => $entryType->fieldLayoutId,
            'authorId'      => 3,
            'title'         => $OrganizationName,
            'postDate'      => new DateTime(),
          ]);
        }

        // Upsert The entry
        $this->saveMemberEntry($entry, $customFields);

      });

    }


  }

  public function saveMemberEntry(Entry $entry , $customFields = array()){
    $fieldValues = [];
    foreach($customFields as $index => $customFieldData){
      if(!is_null($customFieldData)){
        $fieldValues[$index] = $customFieldData;
      }
    }

    $entry->setFieldValues($fieldValues);

    Craft::$app->elements->saveElement($entry);
  }

  public function mime2ext($mime) {
    $mime_map = [
      'image/bmp'                                                                 => 'bmp',
      'image/x-bmp'                                                               => 'bmp',
      'image/x-bitmap'                                                            => 'bmp',
      'image/x-xbitmap'                                                           => 'bmp',
      'image/x-win-bitmap'                                                        => 'bmp',
      'image/x-windows-bmp'                                                       => 'bmp',
      'image/ms-bmp'                                                              => 'bmp',
      'image/x-ms-bmp'                                                            => 'bmp',
      'image/cdr'                                                                 => 'cdr',
      'image/x-cdr'                                                               => 'cdr',
      'image/gif'                                                                 => 'gif',
      'image/x-icon'                                                              => 'ico',
      'image/x-ico'                                                               => 'ico',
      'image/vnd.microsoft.icon'                                                  => 'ico',
      'image/jp2'                                                                 => 'jp2',
      'image/jpx'                                                                 => 'jp2',
      'image/jpm'                                                                 => 'jp2',
      'image/jpeg'                                                                => 'jpeg',
      'image/pjpeg'                                                               => 'jpeg',
      'image/png'                                                                 => 'png',
      'image/x-png'                                                               => 'png',
      'image/vnd.adobe.photoshop'                                                 => 'psd',
      'image/svg+xml'                                                             => 'svg',
      'image/tiff'                                                                => 'tiff',
      'image/webp'                                                                => 'webp',
    ];

    return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
  }

  public function getAddresses($address){
    if(!isset($address['Address']['FullAddress'])) return '';
    return str_replace(array("\r\n", "\r", "\n"), "<br />", $address['Address']['FullAddress']);
  }

  public function getSocialNetworks($data){
    $socialItems = [];
    foreach($data as $network){
      array_push($socialItems, [
        'col1' => strtolower($network['SocialNetwork']['SocialNetworkName']),
        'col2' => $network['SocialNetworkProfileLinkURL']
      ]);
    }
    return $socialItems;
  }

  public function getCompanyAndContactInformation(Collection $collect, $value){
    $data = $collect->where('Name',$value)->first();
    return $data['Value'] ?? false;
  }

  public function getOrganizationDataObject($object, $filter = false, $validation = false, $return = 'array')
  {
    $object = collect($object);
    $data = $object->all();
    if($filter !== false && $validation !== false ){
      $data = $object->filter(function ($v, $k) use ($filter, $validation) {
        return $v[$filter] === $validation;
      })->first();
    }

    if(is_null($data)) return null;
    return $return === 'array'? $data : $data['Value'] ?? null;
  }

  public function makeGuzzleRequest($method, $endpoint, $params = array(), $data = false)
  {

    try {
      $params = count($params) > 0 ? "?" . http_build_query($params) : "";
      $response = $this->guzzleClient->request(
        $method,
        rtrim($this->imisURL, '/') . $endpoint . $params,
        ['headers' =>
          [
            'Authorization' => 'Bearer ' . $this->bearerAccessToken
          ]
        ],
        $data
      );
      if ($response->getStatusCode() === 200) {
        $body = $response->getBody();
        return $this->apiService->parseJson($body->getContents());
      }
    } catch (RequestException $e) {

    }

    return false;
  }

  public function toCamelCase($word) {
    return lcfirst(str_replace(' ','', ucwords(strtr($word, '_-', ' '))));
  }


}
