<?php

namespace Yotpo\Loyalty\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigCollectionFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Yotpo\Loyalty\Model\Logger as YotpoLogger;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public const MODULE_NAME = 'Yotpo_Loyalty';

    //= General Settings
    public const XML_PATH_ALL = "yotpo_loyalty";
    public const XML_PATH_ENABLED = "yotpo_loyalty/general_settings/active";
    public const XML_PATH_DEBUG_MODE_ENABLED = "yotpo_loyalty/general_settings/debug_mode_active";
    public const XML_PATH_SWELL_GUID = "yotpo_loyalty/general_settings/swell_guid";
    public const XML_PATH_SWELL_API_KEY = "yotpo_loyalty/general_settings/swell_api_key";
    //= Sync Settings
    public const XML_PATH_SWELL_SYNC_LIMIT = "yotpo_loyalty/sync_settings/swell_sync_limit";
    public const XML_PATH_SWELL_SYNC_MAX_TRYOUTS = "yotpo_loyalty/sync_settings/swell_sync_max_tryouts";
    public const XML_PATH_KEEP_YOTPO_SYNC_QUEUE = "yotpo_loyalty/sync_settings/keep_yotpo_sync_queue";
    //= Advanced
    public const XML_PATH_SWELL_INSTANCE_ID = "yotpo_loyalty/advanced/swell_instance_id";
    public const XML_PATH_DELETE_USED_COUPONS = "yotpo_loyalty/advanced/delete_used_coupons";
    //= Others
    public const XML_PATH_CURRENCY_OPTIONS_DEFAULT = "currency/options/default";
    public const XML_PATH_SECURE_BASE_URL = "web/secure/base_url";
    public const XML_PATH_UNSECURE_BASE_URL = "web/unsecure/base_url";
    public const XML_PATH_USE_SECURE_IN_FRONTEND = "web/secure/use_in_frontend";

    protected $_initializedRequestParams;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var Emulation
     */
    protected $_appEmulation;

    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var ConfigCollectionFactory
     */
    protected $_configCollectionFactory;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var ProductMetadataInterface
     */
    protected $_magentoFrameworkProductMetadata;

    /**
     * @var ModuleListInterface
     */
    private $_moduleList;

    /**
     * @var DateTimeFactory
     */
    protected $_datetimeFactory;

    /**
     * @var JsonHelper
     */
    protected $_jsonHelper;

    /**
     * @var YotpoLogger
     */
    protected $_yotpoLogger;

    /**
     * @method __construct
     * @param  Context                  $context
     * @param  ObjectManagerInterface   $objectManager
     * @param  StoreManagerInterface    $storeManager
     * @param  Emulation                $appEmulation
     * @param  Request                  $request
     * @param  ConfigCollectionFactory  $configCollectionFactory
     * @param  EncryptorInterface       $encryptor
     * @param  ProductMetadataInterface $magentoFrameworkProductMetadata
     * @param  ModuleListInterface      $moduleList
     * @param  DateTimeFactory          $datetimeFactory
     * @param  JsonHelper               $jsonHelper
     * @param  YotpoLogger              $yotpoLogger
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Emulation $appEmulation,
        Request $request,
        ConfigCollectionFactory $configCollectionFactory,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $magentoFrameworkProductMetadata,
        ModuleListInterface $moduleList,
        DateTimeFactory $datetimeFactory,
        JsonHelper $jsonHelper,
        YotpoLogger $yotpoLogger
    ) {
        parent::__construct($context);
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
        $this->_logger = $context->getLogger();
        $this->_appEmulation = $appEmulation;
        $this->_request = $request;
        $this->_configCollectionFactory = $configCollectionFactory;
        $this->_encryptor = $encryptor;
        $this->_magentoFrameworkProductMetadata = $magentoFrameworkProductMetadata;
        $this->_moduleList = $moduleList;
        $this->_datetimeFactory = $datetimeFactory;
        $this->_jsonHelper = $jsonHelper;
        $this->_yotpoLogger = $yotpoLogger;
    }

    ///////////////////////////
    // Constructor Instances //
    ///////////////////////////

    public function getObjectManager()
    {
        return $this->_objectManager;
    }

    public function getStoreManager()
    {
        return $this->_storeManager;
    }

    public function getLogger()
    {
        return $this->_logger;
    }

    public function getAppEmulation()
    {
        return $this->_appEmulation;
    }

    public function getRequest()
    {
        if ($this->_initializedRequestParams === null) {
            $this->_initializedRequestParams = true;
            $requestData = $this->_request->getRequestData();
            if ($requestData) {
                $this->_request->setParams(array_merge(
                    (array) $this->_request->getParams(),
                    (array) $requestData
                ));
            }
        }
        return $this->_request;
    }

    public function getDatetimeFactory()
    {
        return $this->_datetimeFactory;
    }

    ////////////
    // Config //
    ////////////

    public function getConfig($configPath, $scope = null, $scopeId = null, $skipCahce = false)
    {
        $scope = ($scope === null) ? \Magento\Store\Model\ScopeInterface::SCOPE_STORE : $scope;
        $scopeId = ($scopeId === null) ? $this->getStoreManager()->getStore()->getId() : $scopeId;
        if ($skipCahce) {
            if ($scope === \Magento\Store\Model\ScopeInterface::SCOPE_STORE) {
                $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
            } elseif ($scope === \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE) {
                $scope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
            }
            $collection = $this->_configCollectionFactory->create()
                    ->addFieldToFilter('scope', $scope)
                    ->addFieldToFilter('scope_id', $scopeId)
                    ->addFieldToFilter('path', ['like' => $configPath . '%']);
            if ($collection->count()) {
                return $collection->getFirstItem()->getValue();
            }
        } else {
            return $this->scopeConfig->getValue($configPath, $scope, $scopeId);
        }
    }

    public function getAllConfig($scope = null, $scopeId = null, $skipCahce = false)
    {
        return $this->getConfig(self::XML_PATH_ALL, $scope, $scopeId, $skipCahce);
    }

    public function isEnabled($scope = null, $scopeId = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_ENABLED, $scope, $scopeId, $skipCahce)) ? true : false;
    }

    public function isDebugMode($scope = null, $scopeId = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_DEBUG_MODE_ENABLED, $scope, $scopeId, $skipCahce)) ? true : false;
    }

    public function getSwellGuid($scope = null, $scopeId = null, $skipCahce = false)
    {
        return $this->getConfig(self::XML_PATH_SWELL_GUID, $scope, $scopeId, $skipCahce);
    }

    public function getSwellApiKey($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (($apiKey = $this->getConfig(self::XML_PATH_SWELL_API_KEY, $scope, $scopeId, $skipCahce))) ? $this->_encryptor->decrypt($apiKey) : null;
    }

    public function getSwellSyncLimit($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (($limit = (int)$this->getConfig(self::XML_PATH_SWELL_SYNC_LIMIT, $scope, $scopeId, $skipCahce)) > 0) ? $limit : 0;
    }

    public function getSwellSyncMaxTryouts($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (($max = (int)$this->getConfig(self::XML_PATH_SWELL_SYNC_MAX_TRYOUTS, $scope, $scopeId, $skipCahce)) > 0) ? $max : 0;
    }

    public function getKeepYotpoSyncQueue($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (string) $this->getConfig(self::XML_PATH_KEEP_YOTPO_SYNC_QUEUE, $scope, $scopeId, $skipCahce);
    }

    public function getSwellInstanceId($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (string) $this->getConfig(self::XML_PATH_SWELL_INSTANCE_ID, $scope, $scopeId, $skipCahce);
    }

    public function getDeleteUsedCoupons($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (int) $this->getConfig(self::XML_PATH_DELETE_USED_COUPONS, $scope, $scopeId, $skipCahce);
    }

    public function getDefaultCurrency($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (string) $this->getConfig(self::XML_PATH_CURRENCY_OPTIONS_DEFAULT, $scope, $scopeId, $skipCahce);
    }

    public function getUseSecureInFrontend($scope = null, $scopeId = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_USE_SECURE_IN_FRONTEND, $scope, $scopeId, $skipCahce)) ? true : false;
    }

    public function getSecureBaseUrl($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (string) $this->getConfig(self::XML_PATH_SECURE_BASE_URL, $scope, $scopeId, $skipCahce);
    }

    public function getUnsecureBaseUrl($scope = null, $scopeId = null, $skipCahce = false)
    {
        return (string) $this->getConfig(self::XML_PATH_UNSECURE_BASE_URL, $scope, $scopeId, $skipCahce);
    }

    public function getBaseUrl($scope = null, $scopeId = null, $skipCahce = false)
    {
        return ($this->getUseSecureInFrontend($scope, $scopeId, $skipCahce)) ? $this->getSecureBaseUrl($scope, $scopeId, $skipCahce) : $this->getUnsecureBaseUrl($scope, $scopeId, $skipCahce);
    }

    public function getDomain($scope = null, $scopeId = null, $skipCahce = false)
    {
        return $this->mb_parse_url($this->getBaseUrl($scope, $scopeId, $skipCahce), PHP_URL_HOST);
    }

    public function getFieldPathFromConfigMap(string $path)
    {
        return (($return = (string)$this->getConfig(self::XML_PATH_ALL . '/' . $path))) ? $return : null;
    }

    //= Store Helpers =//

    public function getCurrentStore()
    {
        return $this->getStoreManager()->getStore();
    }

    public function getCurrentStoreId()
    {
        return $this->getCurrentStore()->getId();
    }

    public function getCurrentWebsiteId()
    {
        return $this->getCurrentStore()->getWebsiteId();
    }

    public function getCurrentRootCategory()
    {
        return $this->getCategoryModel()->load($this->getCurrentRootCategoryId());
    }

    public function getCurrentRootCategoryId()
    {
        return $this->getCurrentStore()->getRootCategoryId();
    }

    public function getDefaultStoreId()
    {
        return \Magento\Store\Model\Store::DEFAULT_STORE_ID;
    }

    public function getDefaultAdminCode()
    {
        return \Magento\Store\Model\Store::ADMIN_CODE;
    }

    public function getDefaultScope()
    {
        return \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    }

    public function getStoreBaseUrl($storeId = null)
    {
        $storeId = ($storeId === null) ? $this->getCurrentStoreId() : $storeId;
        return $this->getStoreManager()->getStore($storeId)->getBaseUrl();
    }

    public function getStoreWebsiteBaseUrl($storeId = null)
    {
        $storeId = ($storeId === null) ? $this->getCurrentStoreId() : $storeId;
        return $this->getStoreManager()->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
    }

    public function getStoreDomain($storeId = null)
    {
        return $this->mb_parse_url($this->getStoreBaseUrl($storeId), PHP_URL_HOST);
    }

    public function getDefaultStoreIdByWebsiteId($websiteId = null)
    {
        foreach ($this->getStoreManager()->getWebsites() as $website) {
            if ($website->getId() === $websiteId) {
                return $website->getDefaultStoreId();
                break;
            }
        }
    }

    public function getCurrentUrl()
    {
        return 'http' . ((isset($_SERVER['HTTPS']) && !empty($s['HTTPS']) && $s['HTTPS']=='on') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    public function getCurrentDate()
    {
        return $this->getDatetimeFactory()->create()->gmtDate();
    }

    public function getClientIp()
    {
        return $this->getRemoteAddressInstance()->getRemoteAddress();
    }

    public function getUserAgent()
    {
        return (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    public function getMediaUrl(string $mediaPath, string $filePath)
    {
        return $this->getStoreManager()->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . trim($mediaPath, "/") . "/" . ltrim($filePath, "/");
    }

    public function getStoreIdsBySwellApiKey($swellApiKey = null, $withDefault = false, $returnFirstId = false, $skipDisabled = true)
    {
        $return = [];
        $swellApiKey = ($swellApiKey === null) ? $this->getRequest()->getParam("shared_secret") : (string)$swellApiKey;
        $stores = $this->getStoreManager()->getStores($withDefault);
        foreach ($stores as $key => $store) {
            if ($this->isEnabled(\Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getId()) && $swellApiKey === $this->getSwellApiKey(\Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getId())) {
                $return[] = $store->getId();
            }
        }
        return $return;
    }

    public function getStoreIdBySwellApiKey($swellApiKey = null, $withDefault = false)
    {
        return (($storeId = $this->getStoreIdsBySwellApiKey($swellApiKey, $withDefault, true))) ? $storeId[0] : false;
    }

    public function getWebsiteIdsBySwellApiKey($swellApiKey = null, $withDefault = false)
    {
        $return = [];
        $swellApiKey = ($swellApiKey === null) ? $this->getRequest()->getParam("shared_secret") : (string)$swellApiKey;
        $stores = $this->getStoreManager()->getStores($withDefault);
        foreach ($stores as $key => $store) {
            if ($this->isEnabled(\Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getId()) && $swellApiKey === $this->getSwellApiKey(\Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getId())) {
                $return["_" . $store->getWebsiteId()] = $store->getWebsiteId();
            }
        }
        return array_values($return);
    }

    public function getMagentoVersion()
    {
        return $this->_magentoFrameworkProductMetadata->getVersion();
    }

    public function getModuleVersion()
    {
        return $this->_moduleList->getOne(self::MODULE_NAME)['setup_version'];
    }

    public function getStoreDefaultCurrency($storeId = null)
    {
        return $this->getStoreManager()->getStore($storeId)->getDefaultCurrencyCode();
    }

    //========================================================================//

    public function jsonEncode($data)
    {
        return $this->_jsonHelper->jsonEncode($data);
    }

    public function jsonDecode($data)
    {
        return $this->_jsonHelper->jsonDecode($data);
    }

    public function log($message, $type = "debug", $data = [], $prefix = '[Yotpo_Loyalty Log] ')
    {
        if ($type !== 'debug' || $this->isDebugMode()) {
            switch ($type) {
                case 'error':
                    $this->_logger->error($prefix . json_encode($message), $data);
                    break;
                case 'info':
                    $this->_logger->info($prefix . json_encode($message), $data);
                    break;
                case 'debug':
                default:
                    $this->_logger->debug($prefix . json_encode($message), $data);
                    break;
            }
            $this->_yotpoLogger->info($prefix . json_encode($message), $data);
        }
        return $this;
    }

    /**
     * UTF-8 aware parse_url() replacement.
     * @return array
     */
    public function mb_parse_url($url, $component = -1)
    {
        $enc_url = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) {
                return rawurlencode($matches[0]);
            },
            $url
        );
        $parts = parse_url($enc_url, $component);
        if ($parts === false) {
            throw new \InvalidArgumentException('Malformed URL: ' . $url);
        }
        if (is_array($parts)) {
            foreach ($parts as $name => $value) {
                $parts[$name] = rawurldecode($value);
            }
        } else {
            $parts = rawurldecode($parts);
        }
        return $parts;
    }

    public function strToCamelCase(string $str, string $prefix = '', string $suffix = '')
    {
        return $prefix . str_replace('_', '', ucwords($str, '_')) . $suffix;
    }

    //= App Environment Emulation =//

    /**
     * Start environment emulation of the specified store
     *
     * Function returns information about initial store environment and emulates environment of another store
     *
     * @param integer $storeId
     * @param string $area
     * @param bool $force A true value will ensure that environment is always emulated, regardless of current store
     * @return $this
     */
    public function startEnvironmentEmulation($storeId, $area = \Magento\Framework\App\Area::AREA_FRONTEND, $force = false)
    {
        $this->getAppEmulation()->startEnvironmentEmulation($storeId, $area, $force);
        return $this;
    }

    /**
     * Stop environment emulation
     *
     * Function restores initial store environment
     *
     * @return $this
     */
    public function stopEnvironmentEmulation()
    {
        $this->getAppEmulation()->stopEnvironmentEmulation();
        return $this;
    }

    public function emulateFrontendArea($storeId, $force = false)
    {
        $this->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, $force);
        return $this;
    }

    public function emulateAdminArea($storeId = null, $force = false)
    {
        $storeId = ($storeId === null) ? $this->getDefaultStoreId() : $storeId;
        $this->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_ADMINHTML, $force);
        return $this;
    }

    //=====================================================================================================//
}
