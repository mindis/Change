<?php
namespace Rbs\Commerce\Events\Commands;

use Change\Commands\Events\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Json\Json;

/**
 * @name \Rbs\Commerce\Events\Commands\Listeners
 */
class Listeners implements ListenerAggregateInterface
{
	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the EventManager
	 * implementation will pass this to the aggregate.
	 * @param EventManagerInterface $events
	 * @return void
	 */
	public function attach(EventManagerInterface $events)
	{
		$callback = function (Event $event)
		{
			$commandConfigPath = __DIR__ . '/Assets/config.json';
			return Json::decode(file_get_contents($commandConfigPath), Json::TYPE_ARRAY);
		};
		$events->attach('config', $callback);

		$callback = function ($event)
		{
			(new \Rbs\Catalog\Commands\ExportAttributes())->execute($event);
		};
		$events->attach('rbs_catalog:export-attributes', $callback);

		$callback = function ($event)
		{
			(new \Rbs\Catalog\Commands\ImportAttributes())->execute($event);
		};
		$events->attach('rbs_catalog:import-attributes', $callback);
	}

	/**
	 * Detach all previously attached listeners
	 * @param EventManagerInterface $events
	 * @return void
	 */
	public function detach(EventManagerInterface $events)
	{
		// TODO: Implement detach() method.
	}
}