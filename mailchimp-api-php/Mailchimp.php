<?php
namespace BB\Mailchimp;
class Mailchimp {

    public $apikey;
    public $ch;
    public $root  = 'https://api.mailchimp.com/2.0';
    public $debug = false;

    public static $error_map = array(
        "ValidationError" => "\BB\MailChimp\Mailchimp_ValidationError",
        "ServerError_MethodUnknown" => "\BB\MailChimp\Mailchimp_ServerError_MethodUnknown",
        "ServerError_InvalidParameters" => "\BB\MailChimp\Mailchimp_ServerError_InvalidParameters",
        "Unknown_Exception" => "\BB\MailChimp\Mailchimp_Unknown_Exception",
        "Request_TimedOut" => "\BB\MailChimp\Mailchimp_Request_TimedOut",
        "Zend_Uri_Exception" => "\BB\MailChimp\Mailchimp_Zend_Uri_Exception",
        "PDOException" => "\BB\MailChimp\Mailchimp_PDOException",
        "Avesta_Db_Exception" => "\BB\MailChimp\Mailchimp_Avesta_Db_Exception",
        "XML_RPC2_Exception" => "\BB\MailChimp\Mailchimp_XML_RPC2_Exception",
        "XML_RPC2_FaultException" => "\BB\MailChimp\Mailchimp_XML_RPC2_FaultException",
        "Too_Many_Connections" => "\BB\MailChimp\Mailchimp_Too_Many_Connections",
        "Parse_Exception" => "\BB\MailChimp\Mailchimp_Parse_Exception",
        "User_Unknown" => "\BB\MailChimp\Mailchimp_User_Unknown",
        "User_Disabled" => "\BB\MailChimp\Mailchimp_User_Disabled",
        "User_DoesNotExist" => "\BB\MailChimp\Mailchimp_User_DoesNotExist",
        "User_NotApproved" => "\BB\MailChimp\Mailchimp_User_NotApproved",
        "Invalid_ApiKey" => "\BB\MailChimp\Mailchimp_Invalid_ApiKey",
        "User_UnderMaintenance" => "\BB\MailChimp\Mailchimp_User_UnderMaintenance",
        "Invalid_AppKey" => "\BB\MailChimp\Mailchimp_Invalid_AppKey",
        "Invalid_IP" => "\BB\MailChimp\Mailchimp_Invalid_IP",
        "User_DoesExist" => "\BB\MailChimp\Mailchimp_User_DoesExist",
        "User_InvalidRole" => "\BB\MailChimp\Mailchimp_User_InvalidRole",
        "User_InvalidAction" => "\BB\MailChimp\Mailchimp_User_InvalidAction",
        "User_MissingEmail" => "\BB\MailChimp\Mailchimp_User_MissingEmail",
        "User_CannotSendCampaign" => "\BB\MailChimp\Mailchimp_User_CannotSendCampaign",
        "User_MissingModuleOutbox" => "\BB\MailChimp\Mailchimp_User_MissingModuleOutbox",
        "User_ModuleAlreadyPurchased" => "\BB\MailChimp\Mailchimp_User_ModuleAlreadyPurchased",
        "User_ModuleNotPurchased" => "\BB\MailChimp\Mailchimp_User_ModuleNotPurchased",
        "User_NotEnoughCredit" => "\BB\MailChimp\Mailchimp_User_NotEnoughCredit",
        "MC_InvalidPayment" => "\BB\MailChimp\Mailchimp_MC_InvalidPayment",
        "List_DoesNotExist" => "\BB\MailChimp\Mailchimp_List_DoesNotExist",
        "List_InvalidInterestFieldType" => "\BB\MailChimp\Mailchimp_List_InvalidInterestFieldType",
        "List_InvalidOption" => "\BB\MailChimp\Mailchimp_List_InvalidOption",
        "List_InvalidUnsubMember" => "\BB\MailChimp\Mailchimp_List_InvalidUnsubMember",
        "List_InvalidBounceMember" => "\BB\MailChimp\Mailchimp_List_InvalidBounceMember",
        "List_AlreadySubscribed" => "\BB\MailChimp\Mailchimp_List_AlreadySubscribed",
        "List_NotSubscribed" => "\BB\MailChimp\Mailchimp_List_NotSubscribed",
        "List_InvalidImport" => "\BB\MailChimp\Mailchimp_List_InvalidImport",
        "MC_PastedList_Duplicate" => "\BB\MailChimp\Mailchimp_MC_PastedList_Duplicate",
        "MC_PastedList_InvalidImport" => "\BB\MailChimp\Mailchimp_MC_PastedList_InvalidImport",
        "Email_AlreadySubscribed" => "\BB\MailChimp\Mailchimp_Email_AlreadySubscribed",
        "Email_AlreadyUnsubscribed" => "\BB\MailChimp\Mailchimp_Email_AlreadyUnsubscribed",
        "Email_NotExists" => "\BB\MailChimp\Mailchimp_Email_NotExists",
        "Email_NotSubscribed" => "\BB\MailChimp\Mailchimp_Email_NotSubscribed",
        "List_MergeFieldRequired" => "\BB\MailChimp\Mailchimp_List_MergeFieldRequired",
        "List_CannotRemoveEmailMerge" => "\BB\MailChimp\Mailchimp_List_CannotRemoveEmailMerge",
        "List_Merge_InvalidMergeID" => "\BB\MailChimp\Mailchimp_List_Merge_InvalidMergeID",
        "List_TooManyMergeFields" => "\BB\MailChimp\Mailchimp_List_TooManyMergeFields",
        "List_InvalidMergeField" => "\BB\MailChimp\Mailchimp_List_InvalidMergeField",
        "List_InvalidInterestGroup" => "\BB\MailChimp\Mailchimp_List_InvalidInterestGroup",
        "List_TooManyInterestGroups" => "\BB\MailChimp\Mailchimp_List_TooManyInterestGroups",
        "Campaign_DoesNotExist" => "\BB\MailChimp\Mailchimp_Campaign_DoesNotExist",
        "Campaign_StatsNotAvailable" => "\BB\MailChimp\Mailchimp_Campaign_StatsNotAvailable",
        "Campaign_InvalidAbsplit" => "\BB\MailChimp\Mailchimp_Campaign_InvalidAbsplit",
        "Campaign_InvalidContent" => "\BB\MailChimp\Mailchimp_Campaign_InvalidContent",
        "Campaign_InvalidOption" => "\BB\MailChimp\Mailchimp_Campaign_InvalidOption",
        "Campaign_InvalidStatus" => "\BB\MailChimp\Mailchimp_Campaign_InvalidStatus",
        "Campaign_NotSaved" => "\BB\MailChimp\Mailchimp_Campaign_NotSaved",
        "Campaign_InvalidSegment" => "\BB\MailChimp\Mailchimp_Campaign_InvalidSegment",
        "Campaign_InvalidRss" => "\BB\MailChimp\Mailchimp_Campaign_InvalidRss",
        "Campaign_InvalidAuto" => "\BB\MailChimp\Mailchimp_Campaign_InvalidAuto",
        "MC_ContentImport_InvalidArchive" => "\BB\MailChimp\Mailchimp_MC_ContentImport_InvalidArchive",
        "Campaign_BounceMissing" => "\BB\MailChimp\Mailchimp_Campaign_BounceMissing",
        "Campaign_InvalidTemplate" => "\BB\MailChimp\Mailchimp_Campaign_InvalidTemplate",
        "Invalid_EcommOrder" => "\BB\MailChimp\Mailchimp_Invalid_EcommOrder",
        "Absplit_UnknownError" => "\BB\MailChimp\Mailchimp_Absplit_UnknownError",
        "Absplit_UnknownSplitTest" => "\BB\MailChimp\Mailchimp_Absplit_UnknownSplitTest",
        "Absplit_UnknownTestType" => "\BB\MailChimp\Mailchimp_Absplit_UnknownTestType",
        "Absplit_UnknownWaitUnit" => "\BB\MailChimp\Mailchimp_Absplit_UnknownWaitUnit",
        "Absplit_UnknownWinnerType" => "\BB\MailChimp\Mailchimp_Absplit_UnknownWinnerType",
        "Absplit_WinnerNotSelected" => "\BB\MailChimp\Mailchimp_Absplit_WinnerNotSelected",
        "Invalid_Analytics" => "\BB\MailChimp\Mailchimp_Invalid_Analytics",
        "Invalid_DateTime" => "\BB\MailChimp\Mailchimp_Invalid_DateTime",
        "Invalid_Email" => "\BB\MailChimp\Mailchimp_Invalid_Email",
        "Invalid_SendType" => "\BB\MailChimp\Mailchimp_Invalid_SendType",
        "Invalid_Template" => "\BB\MailChimp\Mailchimp_Invalid_Template",
        "Invalid_TrackingOptions" => "\BB\MailChimp\Mailchimp_Invalid_TrackingOptions",
        "Invalid_Options" => "\BB\MailChimp\Mailchimp_Invalid_Options",
        "Invalid_Folder" => "\BB\MailChimp\Mailchimp_Invalid_Folder",
        "Invalid_URL" => "\BB\MailChimp\Mailchimp_Invalid_URL",
        "Module_Unknown" => "\BB\MailChimp\Mailchimp_Module_Unknown",
        "MonthlyPlan_Unknown" => "\BB\MailChimp\Mailchimp_MonthlyPlan_Unknown",
        "Order_TypeUnknown" => "\BB\MailChimp\Mailchimp_Order_TypeUnknown",
        "Invalid_PagingLimit" => "\BB\MailChimp\Mailchimp_Invalid_PagingLimit",
        "Invalid_PagingStart" => "\BB\MailChimp\Mailchimp_Invalid_PagingStart",
        "Max_Size_Reached" => "\BB\MailChimp\Mailchimp_Max_Size_Reached",
        "MC_SearchException" => "\BB\MailChimp\Mailchimp_MC_SearchException",
        "Goal_SaveFailed" => "\BB\MailChimp\Mailchimp_Goal_SaveFailed",
        "Conversation_DoesNotExist" => "\BB\MailChimp\Mailchimp_Conversation_DoesNotExist",
        "Conversation_ReplySaveFailed" => "\BB\MailChimp\Mailchimp_Conversation_ReplySaveFailed",
        "File_Not_Found_Exception" => "\BB\MailChimp\Mailchimp_File_Not_Found_Exception",
        "Folder_Not_Found_Exception" => "\BB\MailChimp\Mailchimp_Folder_Not_Found_Exception",
        "Folder_Exists_Exception" => "\BB\MailChimp\Mailchimp_Folder_Exists_Exception"
    );

    public function __construct($apikey=null, $opts=array()) {
        if (!$apikey) {
            $apikey = getenv('MAILCHIMP_APIKEY');
        }

        if (!$apikey) {
            $apikey = $this->readConfigs();
        }

        if (!$apikey) {
            throw new Mailchimp_Error('You must provide a MailChimp API key');
        }

        $this->apikey = $apikey;
        $dc           = "us1";

        if (strstr($this->apikey, "-")){
            list($key, $dc) = explode("-", $this->apikey, 2);
            if (!$dc) {
                $dc = "us1";
            }
        }

        $this->root = str_replace('https://api', 'https://' . $dc . '.api', $this->root);
        $this->root = rtrim($this->root, '/') . '/';

        if (!isset($opts['timeout']) || !is_int($opts['timeout'])){
            $opts['timeout'] = 600;
        }
        if (isset($opts['debug'])){
            $this->debug = true;
        }


        $this->ch = curl_init();

        if (isset($opts['CURLOPT_FOLLOWLOCATION']) && $opts['CURLOPT_FOLLOWLOCATION'] === true) {
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        }

        curl_setopt($this->ch, CURLOPT_USERAGENT, 'MailChimp-PHP/2.0.6');
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $opts['timeout']);


        $this->folders = new Mailchimp_Folders($this);
        $this->templates = new Mailchimp_Templates($this);
        $this->users = new Mailchimp_Users($this);
        $this->helper = new Mailchimp_Helper($this);
        $this->mobile = new Mailchimp_Mobile($this);
        $this->conversations = new Mailchimp_Conversations($this);
        $this->ecomm = new Mailchimp_Ecomm($this);
        $this->neapolitan = new Mailchimp_Neapolitan($this);
        $this->lists = new Mailchimp_Lists($this);
        $this->campaigns = new Mailchimp_Campaigns($this);
        $this->vip = new Mailchimp_Vip($this);
        $this->reports = new Mailchimp_Reports($this);
        $this->gallery = new Mailchimp_Gallery($this);
        $this->goal = new Mailchimp_Goal($this);
    }

    public function __destruct() {
        if(is_resource($this->ch)) {
            curl_close($this->ch);
        }
    }

    public function call($url, $params) {
        $params['apikey'] = $this->apikey;

        $params = json_encode($params);
        $ch     = $this->ch;

        curl_setopt($ch, CURLOPT_URL, $this->root . $url . '.json');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);

        $start = microtime(true);
        $this->log('Call to ' . $this->root . $url . '.json: ' . $params);
        if($this->debug) {
            $curl_buffer = fopen('php://memory', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $curl_buffer);
        }

        $response_body = curl_exec($ch);

        $info = curl_getinfo($ch);
        $time = microtime(true) - $start;
        if($this->debug) {
            rewind($curl_buffer);
            $this->log(stream_get_contents($curl_buffer));
            fclose($curl_buffer);
        }
        $this->log('Completed in ' . number_format($time * 1000, 2) . 'ms');
        $this->log('Got response: ' . $response_body);

        if(curl_error($ch)) {
            throw new Mailchimp_HttpError("API call to $url failed: " . curl_error($ch));
        }
        $result = json_decode($response_body, true);

        if(floor($info['http_code'] / 100) >= 4) {
            throw $this->castError($result);
        }

        return $result;
    }

    public function readConfigs() {
        $paths = array('~/.mailchimp.key', '/etc/mailchimp.key');
        foreach($paths as $path) {
            if(file_exists($path)) {
                $apikey = trim(file_get_contents($path));
                if ($apikey) {
                    return $apikey;
                }
            }
        }
        return false;
    }

    public function castError($result) {
        if ($result['status'] !== 'error' || !$result['name']) {
            throw new Mailchimp_Error('We received an unexpected error: ' . json_encode($result));
        }

        $class = (isset(self::$error_map[$result['name']])) ? self::$error_map[$result['name']] : '\BB\MailChimp\Mailchimp_Error';
        return new $class($result['error'], $result['code']);
    }

    public function log($msg) {
        if ($this->debug) {
            error_log($msg);
        }
    }
}

require_once 'Mailchimp/Folders.php';
require_once 'Mailchimp/Templates.php';
require_once 'Mailchimp/Users.php';
require_once 'Mailchimp/Helper.php';
require_once 'Mailchimp/Mobile.php';
require_once 'Mailchimp/Conversations.php';
require_once 'Mailchimp/Ecomm.php';
require_once 'Mailchimp/Neapolitan.php';
require_once 'Mailchimp/Lists.php';
require_once 'Mailchimp/Campaigns.php';
require_once 'Mailchimp/Vip.php';
require_once 'Mailchimp/Reports.php';
require_once 'Mailchimp/Gallery.php';
require_once 'Mailchimp/Goal.php';
require_once 'Mailchimp/Exceptions.php';
