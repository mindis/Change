<?php
namespace Rbs\Generic\Events;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\SharedListenerAggregateInterface;

/**
 * @name \Rbs\Generic\Events\SharedListeners
 */
class SharedListeners implements SharedListenerAggregateInterface
{
	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the SharedEventManager
	 * implementation will pass this to the aggregate.
	 * @param SharedEventManagerInterface $events
	 */
	public function attachShared(SharedEventManagerInterface $events)
	{
		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				$website = $event->getDocument();
				if ($website instanceof \Rbs\Website\Documents\Website)
				{
					(new \Rbs\Website\Events\WebsiteResolver())->changed($event->getApplication());
				}
			}
		};
		$eventNames = array('documents.created', 'documents.updated');
		$events->attach('Rbs_Website_Website', $eventNames, $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Website\Events\PageResolver())->resolve($event);
			}
		};
		$events->attach('Documents', 'http.web.displayPage', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Workflow\Tasks\PublicationProcess\Start())->execute($event);
			}
		};
		$events->attach('Documents', array('documents.created', 'documents.localized.created'), $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Workflow\Tasks\CorrectionPublicationProcess\Start())->execute($event);
			}
		};
		$events->attach('Documents', 'correction.created', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Workflow\Http\Rest\Actions\ExecuteTask())->addTasks($event);
			}
		};
		$events->attach('Documents', 'updateRestResult', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Events\Event)
			{
				(new \Rbs\Seo\Std\ModelConfigurationGenerator())->onPluginSetupSuccess($event);
			}
		};
		$events->attach('Plugin', 'setupSuccess', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Seo\Std\DocumentSeoGenerator())->onDocumentCreated($event);
			}
		};
		$events->attach('Documents', 'documents.created', $callback, 5);

		$callback = function ($event)
		{
			if ($event instanceof \Change\Documents\Events\Event)
			{
				(new \Rbs\Seo\Http\Rest\UpdateDocumentLinks())->addLinks($event);
			}
		};
		$events->attach('Documents', 'updateRestResult', $callback, 5);

		$callback = function ($event)
		{
			if (($event instanceof \Change\Events\Event)
				&& ($eventManagerFactory = $event->getParam('eventManagerFactory')) instanceof \Change\Events\EventManagerFactory
			)
			{
				$genericServices = new \Rbs\Generic\GenericServices($event->getApplication(), $eventManagerFactory, $event->getApplicationServices());
				$event->getServices()->set('genericServices', $genericServices);
			}
		};
		$events->attach(array('Commands', 'JobManager', 'Http.Web', 'Http.Rest'), 'registerServices', $callback, 5);
	}

	/**
	 * Detach all previously attached listeners
	 * @param SharedEventManagerInterface $events
	 */
	public function detachShared(SharedEventManagerInterface $events)
	{
		//TODO
	}
}