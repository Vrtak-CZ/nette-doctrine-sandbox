<?php
/**
 * This file is part of the Nella Framework.
 *
 * Copyright (c) 2006, 2011 Patrik Votoček (http://patrik.votocek.cz)
 *
 * This source file is subject to the GNU Lesser General Public License. For more information please see http://nella-project.org
 */

namespace Nella\Config\Extensions;

use Nette\Config\Configurator,
	Nette\DI\ContainerBuilder,
	Doctrine\Common\Cache\Cache,
	Nette\Framework;

/**
 * Doctrine Nella Framework services.
 *
 * @author	Patrik Votoček
 */
class DoctrineExtension extends \Nette\Config\CompilerExtension
{
	/** @var bool */
	private $skipInitDefaultParameters;

	/**
	 * @param bool
	 */
	public function __construct($skipInitDefaultParameters = FALSE)
	{
		$this->skipInitDefaultParameters = $skipInitDefaultParameters;
	}

	/**
	 * Processes configuration data
	 *
	 * @return void
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();

		if (!$this->skipInitDefaultParameters) {
			$this->initDefaultParameters($container);
		}

		// cache
		$container->addDefinition($this->prefix('cache'))
			->setClass('Nella\Doctrine\Cache', array('@cacheStorage'));

		// metadata driver
		$container->addDefinition($this->prefix('metadataDriver'))
			->setClass('Doctrine\ORM\Mapping\Driver\Driver')
			->setFactory('Nella\Config\Extensions\DoctrineExtension::createMetadataDriver', array(
				$this->prefix('@cache'), '%database.entityDirs%', '%database.useAnnotationNamespace%'
			));

		// logger
		$container->addDefinition($this->prefix('logger'))
			->setClass('Doctrine\DBAL\Logging\SQLLogger')
			->setFactory('Nella\Doctrine\Panel::register');

		// configuration
		$container->addDefinition($this->prefix('configuration'))
			->setClass('Doctrine\ORM\Configuration')
			->setFactory('Nella\Config\Extensions\DoctrineExtension::createConfiguration', array(
				$this->prefix('@metadataDriver'), $this->prefix('@cache'), $this->prefix('@cache'),
				'%database%', $this->prefix('@logger'), '%productionMode%'
			));

		// event manager
		$container->addDefinition($this->prefix('eventManager'))
			->setClass('Doctrine\Common\EventManager')
			->setFactory('Nella\Config\Extensions\DoctrineExtension::createEventManager');

		// connection factory
		$connectionFactory = $container->addDefinition($this->prefix('newConnection'))
			->setClass('Doctrine\DBAL\Connection')
			->setParameters(array('config', 'configuration', 'eventManager' => NULL))
			->setFactory('Nella\Config\Extensions\DoctrineExtension::createConnection', array(
				'%config%', '%configuration%', '%eventManager%'
			))
			->setShared(FALSE);

		// connection from factory
		$container->addDefinition($this->prefix('connection'))
			->setClass('Doctrine\DBAL\Connection')
			->setFactory($connectionFactory, array(
				'%database%', $this->prefix('@configuration'), $this->prefix('@eventManager')
			));

		// entity manager factory
		$emFactory = $container->addDefinition($this->prefix('newEntityManager'))
			->setClass('Doctrine\ORM\EntityManager')
			->setParameters(array('connection', 'configuration', 'eventManager' => NULL))
			->setFactory('Doctrine\ORM\EntityManager::create', array('%connection%', '%configuration%', '%eventManager%'))
			->setShared(FALSE);

		// entity manager from factory
		$container->addDefinition($this->prefix('entityManager'))
			->setClass('Doctrine\ORM\EntityManager')
			->setFactory($emFactory, array(
				$this->prefix('@connection'), $this->prefix('@configuration'), $this->prefix('@eventManager')
			))
			->setAutowired(FALSE);

		// console commands - DBAL
		$container->addDefinition($this->prefix('consoleCommandDBALRunSql'))
			->setClass('Doctrine\DBAL\Tools\Console\Command\RunSqlCommand')
			->addTag('consoleCommnad');
		$container->addDefinition($this->prefix('consoleCommandDBALImport'))
			->setClass('Doctrine\DBAL\Tools\Console\Command\ImportCommand')
			->addTag('consoleCommand');

		// console commands - ORM
		$container->addDefinition($this->prefix('consoleCommandORMCreate'))
			->setClass('Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand')
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandORMUpdate'))
			->setClass('Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand')
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandORMDrop'))
			->setClass('Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand')
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandORMGenerateProxies'))
			->setClass('Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand')
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandORMRunDql'))
			->setClass('Doctrine\ORM\Tools\Console\Command\RunDqlCommand')
			->addTag('consoleCommand');

		// console application
		$container->addDefinition($this->prefix('console'))
			->setClass('Symfony\Component\Console\Application')
			->setFactory('Nella\Config\Extensions\DoctrineExtension::createConsole', array('@container'))
			->setAutowired(FALSE);

		// aliases
		$container->addDefinition('entityManager')
			->setClass('Doctrine\ORM\EntityManager')
			->setFactory('@container::getService', array($this->prefix('entityManager')));
		$container->addDefinition('console')
			->setClass('Symfony\Component\Console\Application')
			->setFactory('@container::getService', array($this->prefix('console')));
	}

	/**
	 * @param \Nette\DI\ContainerBuilder
	 */
	protected function initDefaultParameters(ContainerBuilder $container)
	{
		$container->parameters = \Nette\Utils\Arrays::mergeTree($container->parameters, array(
			'database' => array(
				'proxyDir' => "%appDir%/proxies",
				'proxyNamespace' => 'App\Model\Proxies',
				'entityDirs' => array('%appDir%'),
				'useAnnotationNamespace' => TRUE,
			)
		));
	}

	/**
	 * @param \Doctrine\Common\Cache\Cache
	 * @param array
	 * @param bool
	 * @return \Doctrine\ORM\Mapping\Driver\Driver
	 */
	public static function createMetadataDriver(Cache $cache, array $entityDirs, $useAnnotationNamespace = TRUE)
	{
		\Doctrine\Common\Annotations\AnnotationRegistry::registerFile(
			dirname(\Nette\Reflection\ClassType::from('Doctrine\ORM\Version')->getFileName()).
				"/Mapping/Driver/DoctrineAnnotations.php"
		);

		$reader = new \Doctrine\Common\Annotations\AnnotationReader();
		if (!$useAnnotationNamespace) {
			$reader->setDefaultAnnotationNamespace('Doctrine\ORM\Mapping\\');
			//$reader->addNamespace('Doctrine\ORM\Mapping'); // Doctrine 2.2
		} else // in Doctrine 2.2 removed - fuck! fuck! fuck!
			$reader->setAnnotationNamespaceAlias('Doctrine\ORM\Mapping\\', 'ORM');

		$reader->setIgnoreNotImportedAnnotations(true);
		$reader->setEnableParsePhpImports(false);
		$reader = new \Doctrine\Common\Annotations\CachedReader(
			new \Doctrine\Common\Annotations\IndexedReader($reader), $cache
		);

		return new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($reader, $entityDirs);
	}

	/**
	 * @param \Doctrine\ORM\Mapping\Driver\Driver
	 * @param \Doctrine\Common\Cache\Cache
	 * @param \Doctrine\Common\Cache\Cache
	 * @param array $config
	 * @param \Doctrine\DBAL\Logging\SQLLogger|NULL
	 * @param bool
	 * @return \Doctrine\ORM\Configuration
	 */
	public static function createConfiguration(\Doctrine\ORM\Mapping\Driver\Driver $metadataDriver,
		Cache $metadataCache, Cache $queryCache, array $config,
		\Doctrine\DBAL\Logging\SQLLogger $logger = NULL, $productionMode = FALSE)
	{
		$configuration = new \Doctrine\ORM\Configuration;

		// Cache
		$configuration->setMetadataCacheImpl($metadataCache);
		$configuration->setQueryCacheImpl($queryCache);

		// Metadata
		$configuration->setMetadataDriverImpl($metadataDriver);

		// Proxies
		$configuration->setProxyDir($config['proxyDir']);
		$configuration->setProxyNamespace($config['proxyNamespace']);
		if ($productionMode) {
			$configuration->setAutoGenerateProxyClasses(FALSE);
		} else {
			if ($logger) {
				$configuration->setSQLLogger($logger);
			}
			$configuration->setAutoGenerateProxyClasses(TRUE);
		}

		return $configuration;
	}

	/**
	 * @param \Nette\DI\Container
	 * @return \Doctrine\Common\EventManager
	 */
	public static function createEventManager(\Nette\DI\Container $container)
	{
		$evm = new \Doctrine\Common\EventManager;
		foreach (array_keys($container->findByTag('doctrineListener')) as $name) {
			$evm->addEventSubscriber($container->getService($name));
		}

		return $evm;
	}

	/**
	 * @param array
	 * @param \Doctrine\ORM\Configuration
	 * @param \Doctrine\Common\EventManager|NULL
	 * @return \Doctrine\DBAL\Connection
	 */
	public static function createConnection(array $config, \Doctrine\ORM\Configuration $cfg,
		\Doctrine\Common\EventManager $evm = NULL)
	{
		if (!$evm) {
			$evm = new \Doctrine\Common\EventManager;
		}

		if (isset($config['driver']) && $config['driver'] == 'pdo_mysql' && isset($config['charset'])) {
			$evm->addEventSubscriber(
				new \Doctrine\DBAL\Event\Listeners\MysqlSessionInit($config['charset'])
			);
		}

		return \Doctrine\DBAL\DriverManager::getConnection($config, $cfg, $evm);
	}

	/**
	 * @param \Nette\DI\Container
	 * @param \Symfony\Component\Console\Helper\HelperSet
	 * @return \Symfony\Component\Console\Application
	 */
	public static function createConsole(\Nette\DI\Container $container,
		\Symfony\Component\Console\Helper\HelperSet $helperSet = NULL)
	{
		$app = new \Symfony\Component\Console\Application(
			Framework::NAME . " Command Line Interface", Framework::VERSION
		);

		if (!$helperSet) {
			$helperSet = new \Symfony\Component\Console\Helper\HelperSet;
			$helperSet->set(new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper(
				$container->entityManager
			), 'em');
			$helperSet->set(new \Symfony\Component\Console\Helper\DialogHelper, 'dialog');
		}

		$app->setHelperSet($helperSet);
		$app->setCatchExceptions(FALSE);

		$commands = array();
		foreach (array_keys($container->findByTag('consoleCommand')) as $name) {
			$commands[] = $container->getService($name);
		}
		$app->addCommands($commands);

		return $app;
	}

	/**
	 * Register extension to compiler.
	 *
	 * @param \Nette\Config\Configurator $configurator
	 */
	public static function register(Configurator $configurator)
	{
		$class = get_called_class();
		$configurator->onCompile[] = function(Configurator $configurator, \Nette\Config\Compiler $compiler) use($class) {
			$compiler->addExtension('doctrine', new $class);
		};
	}
}