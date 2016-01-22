<?php
/**
 * Axiell Web Services ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace Finna\ILS\Driver;
use SoapClient, SoapFault, SoapHeader, File_MARC, PDO, PDOException, DOMDocument,
    VuFind\Exception\Date as DateException,
    VuFind\Exception\ILS as ILSException,
    VuFind\I18n\Translator\TranslatorAwareInterface as TranslatorAwareInterface,
    Zend\Validator\EmailAddress as EmailAddressValidator,
    Zend\Session\Container as SessionContainer;
use VuFind\Exception\Date;
use Zend\Db\Sql\Ddl\Column\Boolean;
use Zend\Stdlib\ArrayStack;

/**
 * Axiell Web Services ILS Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class AxiellWebServices extends \VuFind\ILS\Driver\AbstractBase
    implements TranslatorAwareInterface, \Zend\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Configuration Reader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader;

    /**
     * Date formatting object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateFormat;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Arena Member code of the institution
     *
     * @var string
     */
    protected $arenaMember = '';

    /**
     * Wsdl-file for accessing the catalgue section of AWS
     *
     * @var string
     */
    protected $catalogue_wsdl = '';

    /**
     * Wsdl-file for accessing the patron section of AWS
     *
     * @var string
     */
    protected $patron_wsdl = '';

    /**
     * Wsdl-file for accessing the loans section of AWS
     *
     * @var string
     */
    protected $loans_wsdl = '';

    /**
     * Wsdl-file for accessing the payment section of AWS
     *
     * @var string
     */
    protected $payments_wsdl = '';

    /**
     * Wsdl-file for accessing the reservation section of AWS
     *
     * @var string
     */
    protected $reservations_wsdl = '';

    /**
     * Path of the AWS debug log-file
     *
     * @var string
     */
    protected $logFile = '';

    /**
     * Pathname with prefix for logging the duration of AWS calls
     *
     * @var string
     */
    protected $durationLogPrefix = '';

    /**
     * Verbose debug-mode
     *
     * @var Boolean
     */
    protected $verbose = false;

    /**
     * Institution settings for the order of organisations
     *
     * @var string
     */
    protected $holdingsOrganisationOrder;

    /**
     * Institution settings for the order of branches
     *
     * @var string
     */
    protected $holdingsBranchOrder;

    /**
     * Container for storing cached ILS data.
     *
     * @var SessionContainer
     */
    protected $session;

    /**
     * Container for storing cached ILS data.
     *
     * @var array
     */
    protected $soapOptions = [
        'soap_version' => SOAP_1_1,
        'exceptions' => true,
        'trace' => 1,
        'connection_timeout' => 60,
        'typemap' => [
            [
                'type_ns' => 'http://www.w3.org/2001/XMLSchema',
                'type_name' => 'anyType',
                'from_xml' => ['\AxiellWebServices', 'anyTypeToString'],
                'to_xml' => ['\AxiellWebServices', 'stringToAnyType']
            ]
        ]
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateFormat = $dateConverter;
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        return true;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $this->debug("getMyProfile called");

        $username = $patron['cat_username'];
        $cacheId = "patron_" . $username;

        $userCached = $this->getCachedData($cacheId);

        if (null === $userCached) {
            $this->patronLogin($username, $patron['cat_password']);
            return $this->getCachedData($cacheId);
        }

        return $userCached;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['arena_member'])) {
            $this->arenaMember = $this->config['Catalog']['arena_member'];
        } else {
            throw new ILSException('arena_member configuration needs to be set.');
        }

        $confDir = realpath('local/config/vufind/') . '/';

        if (isset($this->config['Catalog']['catalogue_wsdl'])) {
            $this->catalogue_wsdl
                = $confDir . $this->config['Catalog']['catalogue_wsdl'];
        } else {
            throw new ILSException('catalogue_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['patron_wsdl'])) {
            $this->patron_wsdl = $confDir . $this->config['Catalog']['patron_wsdl'];
        } else {
            throw new ILSException('patron_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['loans_wsdl'])) {
            $this->loans_wsdl = $confDir . $this->config['Catalog']['loans_wsdl'];
        } else {
            throw new ILSException('loans_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['payments_wsdl'])) {
            $this->payments_wsdl
                = $confDir . $this->config['Catalog']['payments_wsdl'];
        } else {
            throw new ILSException('payments_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['reservations_wsdl'])) {
            $this->reservations_wsdl
                = $confDir . $this->config['Catalog']['reservations_wsdl'];
        } else {
            throw new
                ILSException('reservations_wsdl configuration needs to be set.');
        }

        $this->defaultPickUpLocation
            = (isset($this->config['Holds']['defaultPickUpLocation']))
            ? $this->config['Holds']['defaultPickUpLocation'] : false;
        if ($this->defaultPickUpLocation == '0') {
            $this->defaultPickUpLocation = false;
        }

        if (isset($this->config['Debug']['durationLogPrefix'])) {
            $this->durationLogPrefix = $this->config['Debug']['durationLogPrefix'];
        }

        if (isset($this->config['Debug']['verbose'])) {
            $this->verbose = $this->config['Debug']['verbose'];
        }

        if (isset($this->config['Debug']['log'])) {
            $this->logFile = $this->config['Debug']['log'];
        }
        $this->holdingsOrganisationOrder
            = isset($this->config['Holdings']['holdingsOrganisationOrder'])
            ? explode(":", $this->config['Holdings']['holdingsOrganisationOrder'])
            : [];
        $this->holdingsOrganisationOrder
            = array_flip($this->holdingsOrganisationOrder);
        $this->holdingsBranchOrder
            = isset($this->config['Holdings']['holdingsBranchOrder'])
            ? explode(":", $this->config['Holdings']['holdingsBranchOrder'])
            : [];
        $this->holdingsBranchOrder = array_flip($this->holdingsBranchOrder);

        // Establish a namespace in the session for persisting cached data
        $this->session
            = new SessionContainer('AxiellWebServices_' . $this->arenaMember);
    }

    /**
     * Helper function for fetching cached data.
     * Data is cached for up to 30 seconds so that it would be faster to process
     * e.g. requests where multiple calls to the backend are made.
     *
     * @param string $id Cache entry id
     *
     * @return mixed|null Cached entry or null if not cached or expired
     */
    protected function getCachedData($id)
    {
        if (isset($this->session->cache[$id])) {
            $item = $this->session->cache[$id];
            if (time() - $item['time'] < 30) {
                return $item['entry'];
            }
            unset($this->session->cache[$id]);
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     * Data is cached for up to 30 seconds so that it would be faster to process
     * e.g. requests where multiple calls to the backend are made.
     *
     * @param string $id    Cache entry id
     * @param mixed  $entry Entry to be cached
     *
     * @return void
     */
    protected function putCachedData($id, $entry)
    {
        if (!isset($this->session->cache)) {
            $this->session->cache = [];
        }
        $this->session->cache[$id] = [
            'time' => time(),
            'entry' => $entry
        ];
    }

    /**
     * Get Pickup Locations
     *
     * This is responsible for retrieving pickup locations.
     *
     * @param array $user        The patron array from patronLogin
     * @param array $holdDetails Hold details
     *
     * @throws ILSException
     *
     * @return array Array of the patron's fines on success
     */
    public function getPickUpLocations($user, $holdDetails)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $id = !empty($holdDetails['item_id'])
            ? $holdDetails['item_id'] : $holdDetails['id'];

        $function = 'getReservationBranches';
        $functionResult = 'getReservationBranchesResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'country' => 'FI',
            'reservationEntities' => $id,
            'reservationType' => 'normal'
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            ['getReservationBranchesParam' => $conf]
        );

        $locationsList = [];
        if (!isset($result->$functionResult->organisations->organisation)) {
            // If we didn't get any pickup locations for item_id, fall back to id
            // and try again... This seems to happen when there are only ordered
            // items in the branch
            if (!empty($holdDetails['item_id'])) {
                unset($holdDetails['item_id']);
                return $this->getPickUpLocations($user, $holdDetails);
            }
            return $locationsList;
        }
        $organisations
            = (array)$result->$functionResult->organisations->organisation;

        foreach ($organisations as $organisation) {
            if (!isset($organisation->branches->branch)) {
                continue;
            }

            $organisationID = $organisation->id;

            // TODO: Make it configurable whether organisation names
            // should be included in the location name
            $branches = (array)$organisation->branches->branch;

            if (is_object($organisation->branches->branch)) {
                $locationsList[] = [
                    'locationID' =>
                       $organisationID . '.' .  $organisation->branches->branch->id,
                    'locationDisplay' => $organisation->branches->branch->name
                ];
            } else {
                foreach ($organisation->branches->branch as $branch) {
                    $locationsList[] = [
                        'locationID' => $organisationID . '.' . $branch->id,
                        'locationDisplay' => $branch->name
                    ];
                }
            }
        }

        // Sort pick up locations
        usort($locationsList, [$this, 'pickUpLocationsSortFunction']);

        return $locationsList;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.
     * May be used to limit the request group options or may be ignored.
     *
     * @return string       The default request group for the patron.
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        $requestGroups = $this->getRequestGroups(0, 0);
        return $requestGroups[0]['id'];
    }

    /**
     * Get request groups
     *
     * @param integer $bibId    BIB ID
     * @param array   $patronId Patron information returned by the patronLogin
     * method.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     */
    public function getRequestGroups($bibId, $patronId)
    {
        // Request Groups are not used for reservations
        return false;
    }

    /**
     * Place Hold
     *
     * This is responsible for both placing holds as well as placing recalls.
     *
     * @param string $holdDetails The request details
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function placeHold($holdDetails)
    {
        if (isset($holdDetails['item_id']) && $holdDetails['item_id']) {
            $entityId = $holdDetails['item_id'];
            $reservationSource = 'holdings';
        } else {
            $entityId = $holdDetails['id'];
            $reservationSource = 'catalogueRecordDetail';
        }

        $username = $holdDetails['patron']['cat_username'];
        $password = $holdDetails['patron']['cat_password'];

        try {
            $validFromDate = date('Y-m-d');

            if (!isset($holdDetails['requiredBy'])) {
                $validToDate = $this->getDefaultRequiredByDate();
            } else {
                $validToDate = $this->dateFormat->convertFromDisplayDate(
                    'Y-m-d', $holdDetails['requiredBy']
                );
            }
        } catch (DateException $e) {
            // Hold Date is invalid
            throw new ILSException('hold_date_invalid');
        }

        $pickUpLocation = $holdDetails['pickUpLocation'];
        list($organisation, $branch) = explode('.', $pickUpLocation, 2);

        $function = 'addReservation';
        $functionResult = 'addReservationResult';
        $functionParam = 'addReservationParam';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'user'         => $username,
            'password'     => $password,
            'language'     => 'en',
            'reservationEntities' => $entityId,
            'reservationSource' => $reservationSource,
            'reservationType' => 'normal',
            'organisationId' => $organisation,
            'pickUpBranchId' => $branch,
            'validFromDate' => $validFromDate,
            'validToDate' => $validToDate
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            [$functionParam => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message
                = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return [
               'success' => false,
               'sysMessage' => $message
            ];
        }

        return [
            'success' => true
        ];
    }

    /**
     * Cancel Holds
     *
     * This is responsible for canceling holds.
     *
     * @param string $cancelDetails The request details
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function cancelHolds($cancelDetails)
    {
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $succeeded = 0;
        $results = [];

        $function = 'removeReservation';
        $functionResult = 'removeReservationResult';

        foreach ($cancelDetails['details'] as $details) {
            $result = $this->doSOAPRequest(
                $this->reservations_wsdl, $function, $functionResult, $username,
                ['removeReservationsParam' =>
                   ['arenaMember' => $this->arenaMember,
                    'user' => $username, 'password' => $password,
                     'language' => 'en', 'id' => $details]
                ]
            );

            $statusAWS = $result->$functionResult->status;

            if ($statusAWS->type != 'ok') {
                $message
                    = $this->handleError($function, $statusAWS->message, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
                $results[$details] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $statusAWS->message
                ];
            } else {
                $results[$details] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                    'sysMessage' => ''
                ];
            }

            ++$succeeded;
        }
        $results['count'] = $succeeded;
        return $results;
    }

    /**
     * Cancel Hold Details
     *
     * This is responsible for getting the details required for canceling holds.
     *
     * @param string $holdDetails The request details
     *
     * @return string           Required details passed to cancelHold
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['reqnum'];
    }

    /**
     * Change pickup location
     *
     * This is responsible for changing the pickup location of a hold
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Associative array of the results
     */
    public function changePickupLocation($patron, $holdDetails)
    {
        global $configArray;
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];
        $pickUpLocation = $holdDetails['pickup'];
        $created = $this->dateFormat->convertFromDisplayDate(
            "Y-m-d", $holdDetails['created']
        );
        $expires = $this->dateFormat->convertFromDisplayDate(
            "Y-m-d", $holdDetails['expires']
        );
        $reservationId = $holdDetails['reservationId'];
        list($organisation, $branch) = explode('.', $pickUpLocation, 2);

        $function = 'changeReservation';
        $functionResult = 'changeReservationResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => 'en',
            'id' => $reservationId,
            'pickUpBranchId' => $branch,
            'validFromDate' => $created,
            'validToDate' => $expires
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            ['changeReservationsParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return [
                'success' => false,
                'sysMessage' => $statusAWS->message
            ];
        }

        return [
            'success' => true
        ];
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getHolding($id);
        }
        return $items;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws DateException
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $function = 'GetHoldings';
        $functionResult = 'GetHoldingResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'id' => $id,
            'language' => $this->getLanguage()
        ];

        $result = $this->doSOAPRequest(
            $this->catalogue_wsdl, $function, $functionResult, $id,
            ['GetHoldingsRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $id);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException('ils_offline_holdings_message');
            }
            return [];
        }

        if (!isset($result->$functionResult->catalogueRecord->compositeHolding)) {
            return [];
        }

        $holdings
            = (array)$result->$functionResult->catalogueRecord->compositeHolding;

        if (isset($holdings[0]->type) && $holdings[0]->type == 'year') {
            $result = [];
            foreach ($holdings as $holding) {
                $year = $holding->value;
                $holdingsEditions = (array)$holding->compositeHolding;
                foreach ($holdingsEditions as $holdingsEdition) {
                    $edition = $holdingsEdition->value;
                    $holdingsOrganisations
                        = (array)$holdingsEdition->compositeHolding;

                    $journalInfo = [
                        'year' => $year,
                        'edition' => $edition
                    ];

                    $result = array_merge(
                        $result,
                        $this->parseHoldings(
                            $holdingsOrganisations, $id, $journalInfo
                        )
                    );
                }
            }
        } else {
            $result = $this->parseHoldings($holdings, $id, '', '');
        }

        if (!empty($result)) {
            usort($result, [$this, 'holdingsSortFunction']);

            $summary = $this->getHoldingsSummary($result);
            $result[] = $summary;
        }

        return empty($result) ? false : $result;
    }

    /**
     * This is responsible for iterating the organisation holdings
     *
     * @param array  $organisationHoldings Organisation holdings
     * @param string $id                   The record id to retrieve the holdings
     * @param array  $journalInfo          Jornal information
     *
     * @return array
     */
    protected function parseHoldings($organisationHoldings, $id, $journalInfo = null)
    {
        if ($organisationHoldings[0]->status == 'noHolding') {
            return;
        }
        if ($organisationHoldings[0]->type != 'organisation') {
            return;
        }

        $result = [];
        foreach ($organisationHoldings as $organisation) {
            $organisationName = $group = $organisation->value;
            $organisationId = $organisation->id;
            $holdingsBranch = (array)$organisation->compositeHolding;
            if ($holdingsBranch[0]->type == 'branch') {
                foreach ($holdingsBranch as $branch) {
                    $branchName = $branch->value;
                    $branchId = $branch->id;
                    $reservableId = isset($branch->reservable)
                        ? $branch->reservable : '';
                    $holdable = $branch->reservationButtonStatus == 'reservationOk';
                    $departments = (array)$branch->holdings->holding;
                    foreach ($departments as $department) {
                        // Get holding data
                        $dueDate = isset($department->firstLoanDueDate)
                            ? $this->dateFormat->convertToDisplayDate(
                                '* M d G:i:s e Y',
                                $department->firstLoanDueDate
                            ) : '';
                        $departmentName = $department->department;
                        $locationName = isset($department->location)
                            ? $department->location : '';

                        if (!empty($locationName)) {
                            $departmentName .= ", $locationName";
                        }

                        $nofAvailableForLoan
                            = isset($department->nofAvailableForLoan)
                            ? $department->nofAvailableForLoan : 0;
                        $nofTotal = isset($department->nofTotal)
                            ? $department->nofTotal : 0;
                        $nofOrdered = isset($department->nofOrdered)
                            ? $department->nofOrdered : 0;

                        // Group journals by issue number
                        if ($journalInfo) {
                            $year = isset($journalInfo['year'])
                                ? $journalInfo['year'] : '';
                            $edition = isset($journalInfo['edition'])
                                ? $journalInfo['edition'] : '';
                            if ($year !== '' && $edition !== '') {
                                if (strncmp($year, $edition, strlen($year)) == 0) {
                                    $group = $edition;
                                } else {
                                    $group = "$year, $edition";
                                }
                            } else {
                                $group = $year . $edition;
                            }
                            $journalInfo['location'] = $organisationName;
                        }

                        // Status & availability
                        $status = $department->status;
                        $available
                            = $status == 'availableForLoan'
                                || $status == 'returnedToday';

                        // Special status: On reference desk
                        if ($status == 'nonAvailableForLoan'
                            && isset($department->nofReference)
                            && $department->nofReference != 0
                        ) {
                            $status = 'onRefDesk';
                            $available = true;
                        }

                        // Status table
                        $statusArray = [
                           'availableForLoan' => 'Available',
                           'onLoan' => 'Charged',
                           //'nonAvailableForLoan' => 'Not Available',
                           'nonAvailableForLoan' => 'On Reference Desk',
                           'onRefDesk' => 'On Reference Desk',
                           'overdueLoan' => 'overdueLoan',
                           'ordered' => 'Ordered',
                           'returnedToday' => 'returnedToday'
                        ];

                        // Convert status text
                        if (isset($statusArray[$status])) {
                            $status = $statusArray[$status];
                        } else {
                            $this->debug(
                                'Unhandled status ' +
                                $department->status +
                                " for '$this->arenaMember'.'$id'"
                            );
                        }

                        $holding = [
                           'id' => $id,
                           'barcode' => $id,
                           'item_id' => $reservableId,
                           'holdings_id' => $group,
                           'availability'
                              => $available || $status == 'On Reference Desk',
                           'availabilityInfo' => [
                               'available' => $nofAvailableForLoan,
                               'displayText' => $status,
                               'reservations' => isset($branch->nofReservations)
                                   ? $branch->nofReservations : 0,
                               'ordered' => $nofOrdered,
                               'total' => $nofTotal,
                            ],
                           'status' => $status,
                           'location' => $group,
                           'organisation_id' => $organisationId,
                           'branch' => $branchName,
                           'branch_id' => $branchId,
                           'department' => $departmentName,
                           'duedate' => $dueDate,
                           'addLink' => $journalInfo,
                           'callnumber' => isset($department->shelfMark)
                               ? ($department->shelfMark) : '',
                           'is_holdable'
                              => $branch->reservationButtonStatus == 'reservationOk',
                           'collapsed' => true,
                           'reserve' => null
                        ];
                        if ($journalInfo) {
                            $holding['journalInfo'] = $journalInfo;
                        }
                        $result[] = $holding;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = $reservationsTotal = 0;
        $locations = [];
        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (isset($item['availabilityInfo']['total'])) {
                $itemsTotal += $item['availabilityInfo']['total'];
            } else {
                $itemsTotal++;
            }
            if (isset($item['availabilityInfo']['reservations'])) {
                $reservations = max(
                    $reservationsTotal,
                    $item['availabilityInfo']['reservations']
                );
            }
            $locations[$item['location']] = true;
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.
        return [
           'available' => $availableTotal,
           'total' => $itemsTotal,
           'reservations' => $reservations,
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => null
        ];
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron barcode
     * @param string $password The patron's last name or PIN (depending on config)
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $arenaMember = $this->arenaMember;
        $cacheId = "patron_" . $username;
        $function = 'getPatronInformation';
        $functionResult = 'patronInformationResult';
        $conf = [
            'arenaMember' => $arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()

        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            ['patronInformationParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_login_message');
            }
            return null;
        }

        $info = $result->$functionResult->patronInformation;

        $names = explode(' ', $info->patronName);
        $lastname = array_pop($names);
        $firstname = implode(' ', $names);

        $user = [
            'id' => $username,
            'cat_username' => $username,
            'cat_password' => $password,
            'lastname' => $lastname,
            'firstname' => $firstname,
            'major' => null,
            'college' => null
        ];

        $userCached = [
            'id' => $username,
            'cat_username' => $username,
            'cat_password' => $password,
            'lastname' => $lastname,
            'firstname' => $firstname,
            'email' => '',
            'emailId' => '',
            'address1' => '',
            'zip' => '',
            'phone' => '',
            'phoneId' => '',
            'phoneLocalCode' => '',
            'phoneAreaCode' => '',
            'major' => null,
            'college' => null
        ];


        if (isset($info->emailAddresses) && $info->emailAddresses->emailAddress) {
            $emailAddresses = (array)$info->emailAddresses->emailAddress;

            foreach ($emailAddresses as $emailAddress) {
                if ($emailAddress->isActive == 'yes') {
                    $userCached['email'] = isset($emailAddress->address)
                        ? $emailAddress->address : '';
                    $userCached['emailId']
                        = isset($emailAddress->id) ? $emailAddress->id : '';
                }
            }
        }

        if (isset($info->addresses)) {
            $addresses = (array)$info->addresses->address;
            foreach ($addresses as $address) {
                if ($address->isActive == 'yes') {
                    $userCached['address1'] = isset($address->streetAddress)
                        ? $address->streetAddress : '';
                    $userCached['zip'] = isset($address->zipCode)
                        ? $address->zipCode : '';
                    if (isset($address->city)) {
                        if ($userCached['zip']) {
                            $userCached['zip'] .= ', ';
                        }
                        $userCached['zip'] .= $address->city;
                    }
                    if (isset($address->country)) {
                        if ($userCached['zip']) {
                            $userCached['zip'] .= ', ';
                        }
                        $userCached['zip'] .= $address->country;
                    }
                }
            }
        }

        if (isset($info->phoneNumbers)) {
            $phoneNumbers = (array)$info->phoneNumbers->phoneNumber;
            foreach ($phoneNumbers as $phoneNumber) {
                if ($phoneNumber->sms->useForSms == 'yes') {
                    $userCached['phone'] = isset($phoneNumber->areaCode)
                        ? $phoneNumber->areaCode : '';
                    $userCached['phoneAreaCode'] = $userCached['phone'];
                    if (isset($phoneNumber->localCode)) {
                        $userCached['phone'] .= $phoneNumber->localCode;
                        $userCached['phoneLocalCode'] = $phoneNumber->localCode;
                    }
                    if (isset($phoneNumber->id)) {
                        $userCached['phoneId'] = $phoneNumber->id;
                    }
                }
            }
        }

        $this->putCachedData($cacheId, $userCached);

        return $user;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'GetLoans';
        $functionResult = 'loansResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()
        ];

        $result = $this->doSOAPRequest(
            $this->loans_wsdl, $function, $functionResult, $username,
            ['loansRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $transList = [];
        if (!isset($result->$functionResult->loans->loan)) {
            return $transList;
        }
        $loans = (array)$result->$functionResult->loans->loan;

        foreach ($loans as $loan) {
            $title = $loan->catalogueRecord->title;
            if ($loan->note) {
                $title .= ' (' . $loan->note . ')';
            }

            $trans = [
                'id' => $loan->catalogueRecord->id,
                'item_id' => $loan->id,
                'title' => $title,
                'duedate' => $loan->loanDueDate,
                'renewable' => (string)$loan->loanStatus->isRenewable == 'yes',
                'message' => $this->mapStatus($loan->loanStatus->status),
                'barcode' => $loan->id,
                'renewalCount' => max(
                    [0,
                        $this->config['Loans']['renewalLimit']
                        - $loan->remainingRenewals]
                ),
                'renewalLimit' => $this->config['Loans']['renewalLimit']
            ];

            $transList[] = $trans;
        }

        // Sort the Loans
        $date = [];
        foreach ($transList as $key => $row) {
            $date[$key] = $row['duedate'];
        }
        array_multisort($date, SORT_ASC, $transList);

        // Convert Axiell format to display date format
        foreach ($transList as &$row) {
            $row['duedate'] = $this->formatDate($row['duedate']);
        }

        return $transList;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'GetDebts';
        $functionResult = 'debtsResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'fromDate' => '1699-12-31',
            'toDate' => time()
        ];

        $result = $this->doSOAPRequest(
            $this->payments_wsdl, $function, $functionResult, $username,
            ['debtsRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $finesList = [];
        if (!isset($result->$functionResult->debts->debt)) {
            return $finesList;
        }
        $debts = (array)$result->$functionResult->debts->debt;

        foreach ($debts as $debt) {
            $fine = [
                'debt_id' => $debt->id,
                'amount' => str_replace(',', '.', $debt->debtAmountFormatted) * 100,
                'checkout' => '',
                'fine' => $debt->debtType . ' - ' . $debt->debtNote,
                'balance' => str_replace(',', '.', $debt->debtAmountFormatted) * 100,
                'createdate' => $this->formatDate($debt->debtDate),
                'duedate' => $debt->debtDate,
            ];
            $finesList[] = $fine;
        }

        // Sort the Fines
        $date = [];
        foreach ($finesList as $key => $row) {
            $date[$key] = $row['duedate'];
        }

        array_multisort($date, SORT_DESC, $finesList);

        // Convert Axiell format to display date format
        foreach ($finesList as &$row) {
            $row['duedate'] = $this->formatDate($row['duedate']);
        }

        return $finesList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'getReservations';
        $functionResult =  'getReservationsResult';

        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()

        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            ['getReservationsParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $holdsList = [];
        if (!isset($result->$functionResult->reservations->reservation)) {
            return $holdsList;
        }
        $reservations = (array)$result->$functionResult->reservations->reservation;

        foreach ($reservations as $reservation) {
            $expireDate = $reservation->reservationStatus == 'fetchable'
                ? $reservation->pickUpExpireDate : $reservation->validToDate;
            $title = isset($reservation->catalogueRecord->title)
                ? $reservation->catalogueRecord->title : '';
            if (isset($reservation->note)) {
                $title .= ' (' . $reservation->note . ')';
            }

            $hold = [
                'id' => $reservation->catalogueRecord->id,
                'type' => $reservation->reservationStatus,
                'location' => $reservation->pickUpBranchId,
                'reqnum' =>
                   ($reservation->isDeletable == 'yes' &&
                       isset($reservation->id)) ? $reservation->id : '',
                'pickupnum' =>
                   isset($reservation->pickUpNo) ? $reservation->pickUpNo : '',
                'expire' => $this->formatDate($expireDate),
                'create' => $this->formatDate($reservation->validFromDate),
                'position' =>
                   isset($reservation->queueNo) ? $reservation->queueNo : '-',
                'available' => $reservation->reservationStatus == 'fetchable',
                'modifiable' => $reservation->reservationStatus == 'active',
                'item_id' => '',
                'reservation_id' => $reservation->id,
                'volume' =>
                   isset($reservation->catalogueRecord->volume)
                       ? $reservation->catalogueRecord->volume : '',
                'publication_year' =>
                   isset($reservation->catalogueRecord->publicationYear)
                       ? $reservation->catalogueRecord->publicationYear : '',
                'title' => $title
            ];
            $holdsList[] = $hold;
        }
        return $holdsList;
    }

    /**
     * Renew Details
     *
     * This is responsible for getting the details required for renewing loans.
     *
     * @param string $checkoutDetails The request details
     *
     * @throws ILSException
     *
     * @return string           Required details passed to renewMyItems
     */
    public function getRenewDetails($checkoutDetails)
    {
        return $checkoutDetails['barcode'];
    }

    /**
     * Renew Items
     *
     * This is responsible for renewing items.
     *
     * @param string $renewDetails The request details
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function renewMyItems($renewDetails)
    {
        $succeeded = 0;
        $results = ['blocks' => [], 'details' => []];

        $username = $renewDetails['patron']['cat_username'];
        $password = $renewDetails['patron']['cat_password'];

        foreach ($renewDetails['details'] as $id) {
            $function = 'RenewLoans';
            $functionResult = 'renewLoansResponse';

            $conf = [
                'arenaMember' => $this->arenaMember,
                'user' => $username,
                'password' => $password,
                'language' => 'en',
                'loans' => [$id]
            ];

            $result = $this->doSOAPRequest(
                $this->loans_wsdl, $function, $functionResult, $username,
                ['renewLoansRequest' => $conf]
            );

            if ($statusAWS->type != 'ok') {
                $message
                    = $this->handleError($function, $statusAWS->message, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
            }

            $status
                = trim($result->$functionResult->loans->loan->loanStatus->status);
            $success = $status === 'isRenewedToday';

            $results['details'][$id] = [
                'success' => $success,
                'status' => $success ? 'Loan renewed' : 'Renewal failed',
                'sysMessage' => $status,
                'item_id' => $id,
                'new_date' => $this->formatDate(
                    $result->$functionResult->loans->loan->loanDueDate
                ),
                'new_time' => ''
            ];
        }
        return $results;
    }

    /**
     * Update patron phone number
     *
     * @param array  $patron Patron array
     * @param string $phone  Phone number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updatePhone($patron, $phone)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $user = $this->getMyProfile($patron);

        $function = '';
        $functionResult = '';
        $functionParam = '';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'areaCode'     => '',
            'country'      => isset($user['phoneCountry'])
                ? $user['phoneCountry'] : 'FI',
            'localCode'    => $phone,
            'useForSms'    => 'yes'
        ];

        if (!empty($user['phoneId'])) {
            $conf['id'] = $user['phoneId'];
            $function = 'changePhone';
            $functionResult = 'changePhoneNumberResult';
            $functionParam = 'changePhoneNumberParam';
        } else {
            $function = 'addPhone';
            $functionResult = 'addPhoneNumberResult';
            $functionParam = 'addPhoneNumberParam';
        }

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            [$functionParam => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
                'sys_message' => $statusAWS->message
            ];
        }

        // Clear patron cache
        $cacheId = "patron_" . $username;
        unset($this->session->cache[$cacheId]);

        return [
                'success' => true,
                'status' => 'Phone number changed',
                'sys_message' => ''
            ];
    }

    /**
     * Set patron email address
     *
     * @param array  $patron Patron array
     * @param String $email  User Email
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateEmail($patron, $email)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $user = $this->getMyProfile($patron);

        $function = '';
        $functionResult = '';
        $functionParam = '';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'address'      => $email,
            'isActive'     => 'yes'
        ];

        if (!empty($user['emailId'])) {
            $conf['id'] = $user['emailId'];
            $function = 'changeEmail';
            $functionResult = 'changeEmailAddressResult';
            $functionParam = 'changeEmailAddressParam';
        } else {
            $function = 'addEmail';
            $functionResult = 'addEmailAddressResult';
            $functionParam = 'addEmailAddressParam';
        }

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            [$functionParam => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
                'sys_message' => $statusAWS->message
            ];
        }

        // Clear patron cache
        $cacheId = "patron_" . $username;
        unset($this->session->cache[$cacheId]);

        return [
                'success' => true,
                'status' => 'Email address changed',
                'sys_message' => '',
            ];
    }

    /**
     * Change pin code
     *
     * @param String $cardDetails Patron card data
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function changePassword($cardDetails)
    {
        $username = $cardDetails['patron']['cat_username'];

        $function = 'changeCardPin';
        $functionResult = 'changeCardPinResult';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'cardNumber'   => $username,
            'cardPin'      => $cardDetails['oldPassword'],
            'newCardPin'   => $cardDetails['newPassword'],
        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            ['changeCardPinParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => $statusAWS->message
            ];
        }

        // Clear patron cache
        $cacheId = "patron_" . $username;
        unset($this->session->cache[$cacheId]);

        return  [
                'success' => true,
                'status' => 'change_password_ok',
            ];
    }

    /**
     * Send a SOAP request
     *
     * @param string $wsdl           Name of the wsdl file
     * @param string $function       Name of the function
     * @param string $functionResult Name of the Result tag
     * @param string $id             Username or record id
     * @param array  $params         Parameters needed for the SOAP call
     *
     * @return object SOAP response
     */
    protected function doSOAPRequest($wsdl, $function, $functionResult, $id, $params)
    {
        $client = new SoapClient($wsdl, $this->soapOptions);

        $this->debug("$function Request for '$this->arenaMember'.'$id'");

        $startTime = microtime(true);
        $result = $client->$function($params);

        if ($this->durationLogPrefix) {
            file_put_contents(
                $this->durationLogPrefix . '_' . $function . '.log',
                date('Y-m-d H:i:s ') . round(microtime(true) - $startTime, 4) . "\n",
                FILE_APPEND
            );
        }

        if ($this->verbose) {
            $this->debug(
                "$function Request: " . $this->formatXML($client->__getLastRequest())
            );
            $this->debug(
                "$function Response: "
                . $this->formatXML($client->__getLastResponse())
            );
        }

        return $result;
    }

    /**
     * Format date
     *
     * @param string $dateString Date as a string
     *
     * @return string Formatted date
     */
    protected function formatDate($dateString)
    {
        // remove timezone from Axiell obscure dateformat
        $date = substr($dateString, 0, strpos("$dateString*", "+"));

        return $this->dateFormat->convertToDisplayDate("Y-m-d", $date);
    }

    /**
     * Pretty-print an XML string
     *
     * @param string $xml XML string
     *
     * @return string Pretty XML string
     */
    protected function formatXML($xml)
    {
        if (!$xml) {
            return $xml;
        }
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        return $dom->saveXML();
    }

    /**
     * Get the language to be used in the interface
     *
     * @return string Language as string
     */
    protected function getLanguage()
    {
        global $interface;
        $language = $this->getTranslatorLocale();

        if (!in_array($language, ['en', 'sv', 'fi'])) {
            $language = 'en';
        }
        return $language;
    }

    /**
     * Handle system status error messages from Axiell Web Services
     *
     * @param string $function Function name
     * @param string $message  Error message
     * @param string $id       Holding Id or patron barcode
     *
     * @return string    Error message as string
     */
    protected function handleError($function, $message, $id)
    {
        $status =  [
            // Axiell system status error messages
            'BackendError'           => 'ils_connection_failed',
            'LocalServiceTimeout'    => 'ils_connection_failed',
            'DatabaseError'          => 'ils_connection_failed',
        ];

        if (isset($status[$message])) {
            $this->debug("$function Request failed for '$this->arenaMember'.'$id'");
            $this->debug("AWS error: '$message'");

            $this->error("$function Request failed for '$this->arenaMember'.'$id'");
            $this->error("AWS error: '$message'");
            return $status[$message];
        } return $message;
    }

    /**
     * Sort function for sorting holdings locations
     * according to organisation and branch
     *
     * @param array $a Holding info
     * @param array $b Holding info
     *
     * @return int
     */
    protected function holdingsSortFunction($a, $b)
    {
        if (isset($a['journalInfo']) && isset($b['journalInfo'])) {
            return $this->journalHoldingsSortFunction($a, $b);
        } else {
            return $this->defaultHoldingsSortFunction($a, $b);
        }
    }

    /**
     * Sort function for sorting journal holdings.
     *
     * @param array $a Holding info
     * @param array $b Holding info
     *
     * @return int
     */
    protected function journalHoldingsSortFunction($a, $b)
    {
        $editionA = $a['journalInfo']['edition'];
        $editionB = $b['journalInfo']['edition'];
        if ($editionA ==  $editionB) {
            $a['location'] = $a['journalInfo']['location'];
            $b['location'] = $b['journalInfo']['location'];
            return $this->defaultHoldingsSortFunction($a, $b);
        } else {
            $a = $this->parseJournalIssue($editionA);
            $b = $this->parseJournalIssue($editionB);

            if (empty($a)) {
                return 1;
            }
            if (empty($b)) {
                return -1;
            }

            if ($a === $b) {
                return 0;
            }

            $cnt = min(count($a), count($b));
            $a = array_slice($a, 0, $cnt);
            $b = array_slice($b, 0, $cnt);

            $f = function ($str) {
                return (reset(explode('-', $str)));
            };

            $a = array_map($f, $a);
            $b = array_map($f, $b);

            return $a > $b ? -1 : 1;
        }
    }

    /**
     * Utility function for parsing journal issue.
     *
     * @param string $issue Journal issue.
     *
     * @return array
     */
    protected function parseJournalIssue($issue)
    {
        $parts = explode(':', $issue);
        return array_map('trim', $parts);
    }

    /**
     * Function for sorting holdings (non-journal)
     * according to organisation and branch.
     *
     * @param array $a Holding info
     * @param array $b Holding info
     *
     * @return int
     */
    protected function defaultHoldingsSortFunction($a, $b)
    {
        if ($a['organisation_id'] !== $b['organisation_id']) {
            $key = 'organisation_id';
            $sortOrder = $this->holdingsOrganisationOrder;
            $locationA = $a['location'];
            $locationB = $b['location'];
        } else {
            $key = 'branch_id';
            $sortOrder = $this->holdingsBranchOrder;
                $locationA
                    = $a['location'] . ' ' . $a['branch'] . ' ' . $a['department'];
                $locationB
                    = $b['location'] . ' ' . $b['branch'] . ' ' . $b['department'];
        }

        $orderA = isset($sortOrder[$a[$key]]) ? $sortOrder[$a[$key]] : null;
        $orderB = isset($sortOrder[$b[$key]]) ? $sortOrder[$b[$key]] : null;

        if ($orderA !== null) {
            if ($orderB !== null) {
                $order = $orderA - $orderB;
                return $order != 0
                    ? $order : strcasecmp($locationA, $locationB);
            }
            return -1;
        }
        if ($orderB !== null) {
            return 1;
        }
        return strcasecmp($locationA, $locationB);
    }

    /**
     * Sort function for sorting pickup locations
     *
     * @param array $a Pickup location
     * @param array $b Pickup location
     *
     * @return number
     */
    protected function pickUpLocationsSortFunction($a, $b)
    {
        $pickUpLocationOrder = isset($this->config['Holds']['pickUpLocationOrder'])
            ? explode(":", $this->config['Holds']['pickUpLocationOrder']) : [];
        $pickUpLocationOrder = array_flip($pickUpLocationOrder);
        if (isset($pickUpLocationOrder[$a['locationID']])) {
            if (isset($pickUpLocationOrder[$b['locationID']])) {
                return
                    $pickUpLocationOrder[$a['locationID']]
                    - $pickUpLocationOrder[$b['locationID']];
            }
            return -1;
        }
        if (isset($pickUpLocationOrder[$b['locationID']])) {
            return 1;
        }
        return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
    }

    /**
     * Map statuses
     *
     * @param string $status as a string
     *
     * @return string Mapped status
     */
    protected function mapStatus($status)
    {
        $statuses =  [
            'isLoanedToday'         => 'Borrowed today',
            'isRenewedToday'        => 'Renewed today',
            'isOverdue'             => 'renew_item_overdue',
            'patronIsDeniedLoan'    => 'fine_limit_patron',
            'patronHasDebt'         => 'renew_item_patron_has_debt',
            'maxNofRenewals'        => 'renew_item_limit',
            'patronIsInvoiced'      => 'renew_item_patron_is_invoiced',
            'copyHasSpecialCircCat' => 'Copy has special circulation',
            'copyIsReserved'        => 'renew_item_requested',
            'renewalIsDenied'       => 'renew_denied'
        ];

        if (isset($statuses[$status])) {
            return $statuses[$status];
        }
        return $status;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        //Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }
}
