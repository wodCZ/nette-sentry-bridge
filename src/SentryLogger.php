<?php

namespace wodCZ\NetteSentryBridge;

use Nette\Application\Application;
use Nette\DI\Container;
use Nette\Security\Identity;
use Nette\Security\User;
use Tracy\Debugger;
use Tracy\Dumper;

class SentryLogger extends \Raven_Processor
{
	/** @var \Raven_Client */
	private $raven;

	/** @var  Container */
	private $container;

	public function __construct($dsn, $options = [])
	{
		if (Debugger::isEnabled() && ! Debugger::$productionMode) {
			return;
		}
		$this->raven = new \Raven_Client($dsn, $options);
		$this->raven->install();
		array_unshift($this->raven->processors, $this);
	}

	public function process(&$data)
	{
		$this->addUserData($data);
		$this->addAppRequests($data);
	}

	/**
	 * @return \Raven_Client
	 */
	public function getClient()
	{
		return $this->raven;
	}

	public function setContainer(Container $container)
	{
		$this->container = $container;
		$container->addService('sentryBridge', $this);
		$this->hookToApplicationOnError();

	}

	private function addUserData(&$data)
	{
		if ( ! $this->container) {
			return;
		}
		try {
			/** @var User $user */
			$user = $this->container->getByType(User::class);
			$identity = $user->getIdentity();
			if ( ! $identity) {
				return;
			}

			if ( ! array_key_exists('user', $data)) {
				$data['user'] = [];
			}
			$data['user']['id'] = $identity->getId() ?: @$data['user']['id'];
			if ($identity instanceof Identity) {
				$data['user']['data'] = array_merge($data['user']['data'], $identity->getData());
			}
		} catch (\Exception $e) {
		}
	}

	private function addAppRequests(&$data)
	{
		if ( ! $this->container) {
			return;
		}
		try {
			/** @var Application $app */
			$app = $this->container->getByType(Application::class);
			$data['extra']['app_requests'] = [];
			foreach ($app->getRequests() as $request) {
				$data['extra']['app_requests'][] = [
					'presenter' => $request->presenterName,
					'method' => $request->method,
					'parameters' => array_map(function ($val) {
						return is_scalar($val) ? $val : Dumper::toText($val);
					}, $request->getParameters()),
				];
			}
			return $data;
		} catch (\Exception $e) {
		}
	}

	private function hookToApplicationOnError()
	{
		try {
			/** @var Application $app */
			$app = $this->container->getByType(Application::class);
			$app->onError[] = function ($app, $e) {
				$this->raven->captureException($e);
			};
		} catch (\Exception $e) {
			$this->raven->captureException($e);
		}
	}
}
