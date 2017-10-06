# nette-sentry-bridge

Bridge that adds some framework-specific context to official Sentry PHP SDK client.

## Installation and usage

### Installation via composer:

```bash
composer require wodcz/nette-sentry-bridge
```

### Usage

This is not usual extension that you would register in `extensions:` section of your config.neon.
In my opinion that is too late (something bad can happen before DIC is initialized).
Because of this, setup is a bit different.

#### `app/bootstrap.php`: 
```php
<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
$configurator = new Nette\Configurator;
//$configurator->setDebugMode('23.75.345.200'); // enable for your remote IP
$configurator->enableTracy(__DIR__ . '/../log');
$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();
$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon');

####################################### PART 1 #######################################
# Try to load configuration from app/config/sentry.php file
if(is_array($config = (@include __DIR__ . '/config/sentry.php')) && @$config['dsn']){
	$logger = new \wodCZ\NetteSentryBridge\SentryLogger($config['dsn'], @$config['options'] ?: []);
}
####################################### PART 1 #######################################

$container = $configurator->createContainer();

####################################### PART 2 #######################################
# Add container instance to logger, so it can pull some info from there.
if (isset($logger)) {
	$logger->setContainer($container);
}
####################################### PART 2 #######################################

return $container;
```

Then, create `app/config/sentry.php` with following configuration:

#### `app/config/sentry.php`: 
```php
<?php
return [
	'dsn' => 'http://key:secret@sentry.example.com/123',
	'options' => [
		'app_path' => __DIR__.'/../',
		'environment' => 'production',
		'exclude' => [
			'Nette\Application\BadRequestException',
			'Nette\Application\ForbiddenRequestException',
			'Nette\Application\AbortException'
		],
		# 'revision' => '',
		# all options: https://docs.sentry.io/clients/php/config/
	]
];
```

Alternative configuration if you use docker or other setup that uses environment variables. If you don't specify dsn
variable, extension will silently do nothing. 

#### `app/config/sentry.php`: 
```php
<?php
return [
	'dsn' => getenv('SENTRY_DSN'),
	'options' => [
		'app_path' => __DIR__.'/../',
		'environment' => getenv('DEBUG') === 'true' ? 'development' : 'production',
		'exclude' => [
			'Nette\Application\BadRequestException',
			'Nette\Application\ForbiddenRequestException',
			'Nette\Application\AbortException'
		],
		# 'revision' => '',
		# all options: https://docs.sentry.io/clients/php/config/
	]
];
```

This is just a sample, all you need to do is to create instance of `\wodCZ\NetteSentryBridge\SentryLogger` 
to start logging, and call `$logger->setContainer($container)` to extend logs with Nette Context 
**and to log errors caught by Nette\Application**

### Important note to prevent confusion

Extension will not log anything if you have tracy enabled.
