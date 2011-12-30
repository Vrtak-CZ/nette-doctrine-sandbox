<?php

/**
 * My Application bootstrap file.
 */
use Nette\Diagnostics\Debugger,
	Nette\Application\Routers\Route;


// Load Nette Framework
require LIBS_DIR . '/Nette/loader.php';


// Enable Nette Debugger for error visualisation & logging
Debugger::$logDirectory = __DIR__ . '/../log';
Debugger::$strictMode = TRUE;
Debugger::enable();


// Configure application
$configurator = new Nette\Config\Configurator;
$configurator->setTempDirectory(__DIR__ . '/../temp');

// Enable RobotLoader - this will load all classes automatically
$configurator->createRobotLoader()
	->addDirectory(APP_DIR)
	->addDirectory(LIBS_DIR)
	->register();

// Register extensions
Nella\Config\Extensions\DoctrineExtension::register($configurator);
Nella\Config\Extensions\DoctrineMigrationsExtension::register($configurator);

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/config.neon');
$container = $configurator->createContainer();

// Opens already started session
if ($container->session->exists()) {
	$container->session->start();
}

// Setup router
$router = $container->router;
$router[] = new Nella\Application\Routers\CliRouter($container);
$router[] = new Route('index.php', 'Homepage:default', Route::ONE_WAY);
$router[] = new Route('<presenter>/<action>[/<id>]', 'Homepage:default');

// Configure and run the application!
$application = $container->application;
if (PHP_SAPI === 'cli') {
	$application->catchExceptions = FALSE;
	$application->allowedMethods = FALSE;
}
//$application->catchExceptions = TRUE;
$application->errorPresenter = 'Error';
$application->run();
