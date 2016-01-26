<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogSearch\Model\ResourceModel\Advanced;

use Magento\Catalog\Model\Product;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorage;

/**
 * Collection Advanced
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * List Of filters
     * @var array
     */
    private $filters = [];

    /**
     * @var \Magento\CatalogSearch\Model\Advanced\Request\Builder
     */
    private $requestBuilder;

    /**
     * @var \Magento\Search\Api\SearchInterface
     */
    private $search;

    /**
     * @var \Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory
     */
    private $temporaryStorageFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactory $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Eav\Model\EntityFactory $eavEntityFactory
     * @param \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Validator\UniversalFactory $universalFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Manager $moduleManager ,
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Url $catalogUrl
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param \Magento\Customer\Api\GroupManagementInterface $groupManagement
     * @param \Magento\CatalogSearch\Model\Advanced\Request\Builder $requestBuilder
     * @param \Magento\Search\Api\SearchInterface $search
     * @param \Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory $temporaryStorageFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param \Zend_Db_Adapter_Abstract|null $connection
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Validator\UniversalFactory $universalFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \Magento\Catalog\Model\ResourceModel\Url $catalogUrl,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        \Magento\CatalogSearch\Model\Advanced\Request\Builder $requestBuilder,
        \Magento\Search\Api\SearchInterface $search,
        \Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory $temporaryStorageFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        $connection = null
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->search = $search;
        $this->temporaryStorageFactory = $temporaryStorageFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $eavConfig,
            $resource,
            $eavEntityFactory,
            $resourceHelper,
            $universalFactory,
            $storeManager,
            $moduleManager,
            $catalogProductFlatState,
            $scopeConfig,
            $productOptionFactory,
            $catalogUrl,
            $localeDate,
            $customerSession,
            $dateTime,
            $groupManagement,
            $connection
        );
    }

    /**
     * Add not indexable fields to search
     *
     * @param array $fields
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addFieldsToFilter($fields)
    {
        if ($fields) {
            $this->filters = array_merge($this->filters, $fields);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function _renderFiltersBefore()
    {
        if ($this->filters) {
            foreach ($this->filters as $attributes) {
                foreach ($attributes as $attributeCode => $attributeValue) {
                    $attributeCode = $this->getAttributeCode($attributeCode);
                    $this->filterBuilder->setField($attributeCode);
                    $this->filterBuilder->setValue($attributeValue);
                    $this->searchCriteriaBuilder->addFilter($this->filterBuilder->create());
                }
            }
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $searchCriteria->setRequestName('advanced_search_container');
            $searchResult = $this->search->search($searchCriteria);

            $temporaryStorage = $this->temporaryStorageFactory->create();
            $table = $temporaryStorage->storeApiDocuments($searchResult->getItems());

            $this->getSelect()->joinInner(
                [
                    'search_result' => $table->getName(),
                ],
                'e.entity_id = search_result.' . TemporaryStorage::FIELD_ENTITY_ID,
                []
            );
        }
        parent::_renderFiltersBefore();
    }

    /**
     * @param string $attributeCode
     * @return string
     */
    private function getAttributeCode($attributeCode)
    {
        if (is_numeric($attributeCode)) {
            $attributeCode = $this->_eavConfig->getAttribute(Product::ENTITY, $attributeCode)
                ->getAttributeCode();
        }

        return $attributeCode;
    }
}
