<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\User\Blocks;

use Change\Presentation\Blocks\Event;
use Change\Presentation\Blocks\Parameters;
use Change\Presentation\Blocks\Standard\Block;

/**
 * @name \Rbs\User\Blocks\Login
 */
class Login extends Block
{
	/**
	 * @api
	 * Set Block Parameters on $event
	 * Required Event method: getBlockLayout, getApplication, getApplicationServices, getServices, getHttpRequest
	 * Optional Event method: getHttpRequest
	 * @param Event $event
	 * @return Parameters
	 */
	protected function parameterize($event)
	{
		$parameters = parent::parameterize($event);
		$parameters->addParameterMeta('errId');
		$parameters->addParameterMeta('login');
		$parameters->addParameterMeta('password');
		$parameters->addParameterMeta('rememberMe');
		$parameters->addParameterMeta('realm', 'web');
		$parameters->addParameterMeta('accessorId');

		$parameters->setLayoutParameters($event->getBlockLayout());
		$request = $event->getHttpRequest();

		$parameters->setParameterValue('errId', $request->getQuery('errId'));

		$login = $request->getPost('login');
		if ($login)
		{
			$parameters->setParameterValue('login', $login);
		}

		$password = $request->getPost('password');
		if ($password)
		{
			$parameters->setParameterValue('password', $password);
		}

		$user = $event->getAuthenticationManager()->getCurrentUser();
		if ($user->authenticated())
		{
			$parameters->setParameterValue('accessorId', $user->getId());
			$parameters->setParameterValue('accessorName', $user->getName());
		}
		return $parameters;
	}

	/**
	 * Set $attributes and return a twig template file name OR set HtmlCallback on result
	 * Required Event method: getBlockLayout, getBlockParameters, getApplication, getApplicationServices, getServices, getHttpRequest
	 * @param Event $event
	 * @param \ArrayObject $attributes
	 * @return string|null
	 */
	protected function execute($event, $attributes)
	{
		$parameters = $event->getBlockParameters();

		// Handle errors.
		$errId = $parameters->getParameterValue('errId');
		if ($errId)
		{
			$session = new \Zend\Session\Container('Change_Errors');
			$sessionErrors = isset($session[$errId]) ? $session[$errId] : null;
			if ($sessionErrors && is_array($sessionErrors))
			{
				$attributes['errors'] = isset($sessionErrors['errors']) ? $sessionErrors['errors'] : [];
				$attributes['login'] = isset($sessionErrors['login']) ? $sessionErrors['login'] : '';
				$attributes['rememberMe'] = isset($sessionErrors['rememberMe']);
			}
		}
		else
		{
			$attributes['login'] = $parameters->getParameterValue('login');
		}

		return 'login.twig';
	}
}