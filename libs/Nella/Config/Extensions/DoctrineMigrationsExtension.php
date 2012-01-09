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
	Nette\DI\ContainerBuilder;

/**
 * Doctrine migration Nella Framework services.
 *
 * @author	Patrik Votoček
 */
class DoctrineMigrationsExtension extends \Nette\Config\CompilerExtension
{
	/** @var string */
	private $doctrineExtensionPrefix;
	/** @var bool */
	private $skipInitDefaultParameters;

	/**
	 * @param string
	 * @param bool
	 */
	public function __construct($doctrineExtensionPrefix = 'doctrine', $skipInitDefaultParameters = FALSE)
	{
		$this->doctrineExtensionPrefix = $doctrineExtensionPrefix;
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

		// console output
		$container->addDefinition($this->prefix('consoleOutput'))
			->setClass('Doctrine\DBAL\Migrations\OutputWriter')
			->setFactory('Nella\Config\Extensions\DoctrineMigrationsExtension::createConsoleOutput')
			->setAutowired(FALSE);

		// migration configuration
		$container->addDefinition($this->prefix('configuration'))
			->setClass('Doctrine\DBAL\Migrations\Configuration\Configuration', array(
				"@{$this->doctrineExtensionPrefix}_connection", $this->prefix('@consoleOutput'))
			)
			->addSetup('setName', array('%database.migrations.name%'))
			->addSetup('setMigrationsTableName', array('%database.migrations.table%'))
			->addSetup('setMigrationsDirectory', array('%database.migrations.directory%'))
			->addSetup('setMigrationsNamespace', array('%database.migrations.namespace%'));

		// console commands
		$container->addDefinition($this->prefix('consoleCommandDiff'))
			->setClass('Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand')
			->addSetup('setMigrationConfiguration', array($this->prefix('@configuration')))
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandExecute'))
			->setClass('Doctrine\DBAL\Migrations\Tools\Console\Command\ExecuteCommand')
			->addSetup('setMigrationConfiguration', array($this->prefix('@configuration')))
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandGenerate'))
			->setClass('Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand')
			->addSetup('setMigrationConfiguration', array($this->prefix('@configuration')))
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandMigrate'))
			->setClass('Doctrine\DBAL\Migrations\Tools\Console\Command\MigrateCommand')
			->addSetup('setMigrationConfiguration', array($this->prefix('@configuration')))
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandStatus'))
			->setClass('Doctrine\DBAL\Migrations\Tools\Console\Command\StatusCommand')
			->addSetup('setMigrationConfiguration', array($this->prefix('@configuration')))
			->addTag('consoleCommand');
		$container->addDefinition($this->prefix('consoleCommandVersion'))
			->setClass('Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand')
			->addSetup('setMigrationConfiguration', array($this->prefix('@configuration')))
			->addTag('consoleCommand');
	}

	/**
	 * @param \Nette\DI\ContainerBuilder
	 */
	protected function initDefaultParameters(ContainerBuilder $container)
	{
		$container->parameters = \Nette\Utils\Arrays::mergeTree($container->parameters, array(
			'database' => array(
				'migrations' => array(
					'name' => \Nette\Framework::NAME . " DB Migrations",
					'table' => "db_version",
					'directory' => "%appDir%/migrations",
					'namespace' => 'App\Model\Migrations',
				)
			)
		));
	}

	/**
	 * @return \Symfony\Component\Console\Output\ConsoleOutput
	 */
	public static function createConsoleOutput()
	{
		$output = new \Symfony\Component\Console\Output\ConsoleOutput;
		return new \Doctrine\DBAL\Migrations\OutputWriter(function($message) use($output) {
			$output->write($message, TRUE);
		});
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
			$compiler->addExtension('migration', new $class);
		};
	}
}