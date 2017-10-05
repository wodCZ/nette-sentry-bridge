<?php
/*
 * Copyright (C) 2015 Adam Schubert <adam.schubert@sg1-game.net>.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either
 * version 3.0+ of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *  General Public License for more details.
 *
 * You should have received a copy of the GNU General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301  USA
 *
 * OR
 *
 * Copyright (C) 2015, Adam Schubert
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * * Neither the name of test nor the names of its
 *  contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace wodCZ\NetteSentryBridge;

use Nette\Application\Application;
use Nette\DI\Container;
use Nette\Security\Identity;
use Nette\Security\User;
use Tracy\Debugger;

class SentryLogger extends \Raven_Processor
{
	/** @var \Raven_Client */
	private $raven;

	/** @var  Container */
	private $container;

	public function __construct($dsn, $options = [])
	{
		if ( ! Debugger::$productionMode) {
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
					'parameters' => $request->getParameters(),
				];
			}
			return $data;
		} catch (\Exception $e) {
		}
	}
}
