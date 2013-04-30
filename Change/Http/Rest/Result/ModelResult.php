<?php
namespace Change\Http\Rest\Result;

use Change\Http\Result;
use Change\Http\UrlManager;

/**
 * @name \Change\Http\Rest\Result\ModelResult
 */
class ModelResult extends Result
{
	/**
	 * @var Links
	 */
	protected $links;

	/**
	 * @var array
	 */
	protected $properties;

	/**
	 * @var array
	 */
	protected $metas;

	/**
	 * @param UrlManager $urlManager
	 */
	public function __construct(UrlManager $urlManager)
	{
		$this->links = new Links();
	}

	/**
	 * @return \Change\Http\Rest\Result\Links
	 */
	public function getLinks()
	{
		return $this->links;
	}

	/**
	 * @param \Change\Http\Rest\Result\Link|array $link
	 */
	public function addLink($link)
	{
		$this->links[] = $link;
	}

	/**
	 * @param array $properties
	 */
	public function setProperties($properties)
	{
		$this->properties = $properties;
	}

	/**
	 * @return array
	 */
	public function getProperties()
	{
		return $this->properties;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function setProperty($name, $value)
	{
		$this->properties[$name] = $value;
	}

	/**
	 * @param array $properties
	 */
	public function setMetas($properties)
	{
		$this->metas = $properties;
	}

	/**
	 * @return array
	 */
	public function getMetas()
	{
		return $this->metas;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function setMeta($name, $value)
	{
		$this->metas[$name] = $value;
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		$array =  array();
		$links = $this->getLinks();
		$array['links'] = $links->toArray();
		$array['metas'] = $this->metas;
		$array['properties'] = $this->properties;
		return $array;
	}
}