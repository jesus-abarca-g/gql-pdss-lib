<?php

namespace GPDCore\Services;

use DateTime;
use Exception;
use DateTimeImmutable;
use DateTimeInterface;
use GraphQL\Doctrine\Types;
use Doctrine\ORM\EntityManager;
use GPDCore\Library\IContextService;
use GPDCore\Graphql\Types\DateTimeType;
use GPDCore\Graphql\Types\QueryJoinType;
use GPDCore\Graphql\Types\QuerySortType;
use GPDCore\Factory\EntityManagerFactory;
use GPDCore\Graphql\ConnectionTypeFactory;
use GPDCore\Graphql\Types\QueryFilterType;
use Laminas\ServiceManager\ServiceManager;
use GPDCore\Graphql\Types\QueryFilterLogic;
use GPDCore\Graphql\Types\QueryJoinTypeValue;
use GPDCore\Graphql\Types\QuerySortDirection;
use GPDCore\Graphql\Types\DateTimeImmutableType;
use GPDCore\Graphql\Types\QueryFilterConditionType;
use GPDCore\Graphql\Types\QueryFilterConditionTypeValue;

class ContextService implements IContextService
{



    const SM_PAGE_INFO = 'PageInfo';
    const SM_PAGE_INFO_INPUT = 'PaginationInput';
    const SM_DATETIME = 'datetime';
    const SM_DATE = 'date';
    const SM_ENTITY_MANAGER = 'entityManager';
    const SM_CONFIG = 'config';



    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Types
     */
    protected $types;

    /**
     * Determina si la app se esta ejecutando en modo producción
     *
     * @var bool
     */
    protected $productionMode;
    protected $enviroment;

    protected $configFile = __DIR__ . "/../../../../../../config/doctrine.local.php";
    protected $cacheDir = __DIR__ . "/../../../../../../data/DoctrineORMModule";
    protected $hasBeenInitialized = false;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }
    public function init(string $enviroment, bool $productionMode): void
    {
        if ($this->hasBeenInitialized) {
            throw new Exception("Context can be initialized just once");
        }
        $this->enviroment = $enviroment;
        $this->productionMode = $productionMode;
        $this->setEntityManager();
        $this->setTypes();
        $this->addTypes();
        $this->hasBeenInitialized = true;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
    public function getConfig(): ConfigService
    {
        return ConfigService::getInstance();
    }
    public function getTypes(): Types
    {
        return $this->types;
    }
    public function getServiceManager(): ServiceManager
    {
        return $this->serviceManager;
    }
    protected function setEntityManager()
    {

        $configFile = $this->configFile;
        if (file_exists($configFile)) {
            $options = require $configFile;
        } else {
            return [];
        }
        $isDevMode = !$this->productionMode;
        $this->entityManager = EntityManagerFactory::createInstance($options, $this->cacheDir, $isDevMode);
    }
    protected function setTypes()
    {
        TypesService::init($this->entityManager, $this->serviceManager);
        $this->types = TypesService::getInstance();
    }

    protected function addTypes()
    {
        $this->addInvokablesToServiceManager();
        $this->addFactoriesToServiceManager();
        $this->addAliasesToServiceManager();
    }

    protected function addInvokablesToServiceManager()
    {
        $this->serviceManager->setInvokableClass(DateTime::class,  DateTimeType::class);
        $this->serviceManager->setInvokableClass(DateTimeImmutable::class,  DateTimeImmutableType::class);
        $this->serviceManager->setInvokableClass(QueryFilterLogic::SM_NAME,  QueryFilterLogic::class);
        $this->serviceManager->setInvokableClass(QueryFilterConditionTypeValue::SM_NAME,  QueryFilterConditionTypeValue::class);
        $this->serviceManager->setInvokableClass(QuerySortDirection::SM_NAME,  QuerySortDirection::class);
        $this->serviceManager->setInvokableClass(QueryJoinTypeValue::SM_NAME,  QueryJoinTypeValue::class);
    }

    protected function addFactoriesToServiceManager()
    {
        $this->serviceManager->setFactory(static::SM_PAGE_INFO, function () {
            return ConnectionTypeFactory::getPageInfoType();
        });
        $this->serviceManager->setFactory(static::SM_PAGE_INFO_INPUT, function () {
            return ConnectionTypeFactory::getPaginationInput();
        });
        $this->serviceManager->setFactory(QueryFilterConditionType::SM_NAME, function ($sm) {
            return new QueryFilterConditionType($sm);
        });
        $this->serviceManager->setFactory(QueryFilterType::SM_NAME, function ($sm) {
            return new QueryFilterType($sm);
        });
        $this->serviceManager->setFactory(QuerySortType::SM_NAME, function ($sm) {
            return new QuerySortType($sm);
        });
        $this->serviceManager->setFactory(QueryJoinType::SM_NAME, function ($sm) {
            return new QueryJoinType($sm);
        });
    }
    protected function addAliasesToServiceManager()
    {
        $this->serviceManager->setAlias(static::SM_DATETIME, DateTime::class); // Declare alias for Doctrine type to be used for filters
        $this->serviceManager->setAlias(static::SM_DATE, DateTime::class); // Declare alias for Doctrine type to be used for filters
        $this->serviceManager->setAlias(DateTimeInterface::class, DateTime::class);
    }

    /**
     * Get the value of configFile
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }

    /**
     * Set the value of configFile
     *
     * @return  self
     */
    public function setConfigFile($configFile)
    {
        $this->configFile = $configFile;

        return $this;
    }

    /**
     * Get the value of cacheDir
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Set the value of cacheDir
     *
     * @return  self
     */
    public function setCacheDir($cacheDir)
    {
        $this->cacheDir = $cacheDir;

        return $this;
    }

    /**
     * Get determina si la app se esta ejecutando en modo producción
     *
     * @return  bool
     */
    public function isProductionMode(): bool
    {
        return $this->productionMode;
    }
}
