<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Website\Blocks;

use Change\Presentation\Blocks\Event;
use Change\Presentation\Blocks\Parameters;
use Change\Presentation\Blocks\Standard\Block;

/**
 * @name \Rbs\Website\Blocks\Menu
 */
class Menu extends Block
{
	/**
	 * @var \Change\I18n\I18nManager
	 */
	protected $i18nManager;

	/**
	 * @var \Change\Http\Web\UrlManager
	 */
	protected $urlManager;

	/**
	 * @api
	 * Set Block Parameters on $event
	 * Required Event method: getBlockLayout, getApplication, getApplicationServices, getServices, getHttpRequest
	 * Event params includes all params from Http\Event (ex: pathRule and page).
	 * @param Event $event
	 * @return Parameters
	 */
	protected function parameterize($event)
	{
		$parameters = parent::parameterize($event);
		$parameters->addParameterMeta('templateName', 'menu-vertical.twig');
		$parameters->addParameterMeta('showTitle', false);
		$parameters->addParameterMeta('contextual', false);
		$parameters->addParameterMeta(static::DOCUMENT_TO_DISPLAY_PROPERTY_NAME);
		$parameters->addParameterMeta('maxLevel', 1);
		$parameters->addParameterMeta('pageId');
		$parameters->addParameterMeta('sectionId');
		$parameters->addParameterMeta('websiteId');

		$parameters->setLayoutParameters($event->getBlockLayout());
		$page = $event->getParam('page');
		if ($page instanceof \Rbs\Website\Documents\Page)
		{
			$parameters->setParameterValue('pageId', $page->getId());
			$parameters->setParameterValue('sectionId', $page->getSection()->getId());
			$parameters->setParameterValue('websiteId', $page->getSection()->getWebsite()->getId());
		}

		if (!$parameters->getParameter(static::DOCUMENT_TO_DISPLAY_PROPERTY_NAME) && $parameters->getParameter('contextual'))
		{
			$parameters->setParameterValue(static::DOCUMENT_TO_DISPLAY_PROPERTY_NAME, $parameters->getParameter('sectionId'));
		}
		else
		{
			$document = $event->getApplicationServices()->getDocumentManager()
				->getDocumentInstance($parameters->getParameter(static::DOCUMENT_TO_DISPLAY_PROPERTY_NAME));
			if (!$this->isValidDocument($document))
			{
				$parameters->setParameterValue(static::DOCUMENT_TO_DISPLAY_PROPERTY_NAME, null);
			}
		}


		return $parameters;
	}

	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @return boolean
	 */
	protected function isValidDocument($document)
	{
		if (($document instanceof \Rbs\Website\Documents\Menu && $document->activated()) || ($document instanceof \Rbs\Website\Documents\Section && $document->published()))
		{
			return true;
		}
		return false;
	}

	/**
	 * @api
	 * Set $attributes and return a twig template file name OR set HtmlCallback on result
	 * Required Event method: getBlockLayout, getBlockParameters, getApplication, getApplicationServices, getServices, getHttpRequest
	 * @param Event $event
	 * @param \ArrayObject $attributes
	 * @return string|null
	 */
	protected function execute($event, $attributes)
	{
		$this->applicationServices = $event->getApplicationServices();
		$dm = $event->getApplicationServices()->getDocumentManager();
		$parameters = $event->getBlockParameters();
		$doc = $dm->getDocumentInstance($parameters->getParameter(static::DOCUMENT_TO_DISPLAY_PROPERTY_NAME));
		if ($doc !== null)
		{
			/* @var $website \Rbs\Website\Documents\Website */
			$website = $dm->getDocumentInstance($parameters->getWebsiteId());
			/* @var $page \Rbs\Website\Documents\Page */
			$page = $dm->getDocumentInstance($parameters->getPageId());
			/* @var $section \Rbs\Website\Documents\Section */
			$section = $dm->getDocumentInstance($parameters->getSectionId());
			if ($section)
			{
				$path = $section->getSectionThread();
			}
			else
			{
				$path = array();
			}
			$this->urlManager = $event->getUrlManager();
			$this->i18nManager = $event->getApplicationServices()->getI18nManager();
			$attributes['root'] = $this->getMenuEntry($website, $doc, $parameters->getMaxLevel(), $page, $path);
			$attributes['uniqueId'] = uniqid();
			return $parameters->getTemplateName();
		}
		return null;
	}

	/**
	 * @var \Change\Services\ApplicationServices
	 */
	protected $applicationServices;

	/**
	 * @return \Change\Services\ApplicationServices
	 */
	protected function getApplicationServices()
	{
		return $this->applicationServices;
	}

	/**
	 * @param \Change\Presentation\Interfaces\Website $website
	 * @param \Change\Documents\AbstractDocument $doc
	 * @param integer $maxLevel
	 * @param null|\Rbs\Website\Documents\Page $currentPage
	 * @param \Rbs\Website\Documents\Section[] $path
	 * @return \Rbs\Website\Menu\MenuEntry|null
	 */
	protected function getMenuEntry($website, $doc, $maxLevel, $currentPage, $path)
	{
		if (!$this->shouldBeDisplayed($doc))
		{
			return null;
		}

		$entry = new \Rbs\Website\Menu\MenuEntry();
		$entry->setTitle($doc->getDocumentModel()->getPropertyValue($doc, 'title'));

		if (!$doc instanceof \Rbs\Website\Documents\Menu)
		{
			if ($doc instanceof \Rbs\Website\Documents\Section)
			{
				$indexPage = $doc->getIndexPage();
				if ($indexPage instanceof \Change\Documents\Interfaces\Publishable && $indexPage->published())
				{
					$entry->setUrl($this->urlManager->getCanonicalByDocument($doc, $website));
				}
				elseif ($maxLevel < 1)
				{
					return null; // Hide empty topics.
				}
				if (count($path) && in_array($doc, $path))
				{
					$entry->setInPath(true);
				}
			}
			else
			{
				$entry->setUrl($this->urlManager->getCanonicalByDocument($doc, $website));
				if ($currentPage === $doc)
				{
					$entry->setCurrent(true);
					$entry->setInPath(true);
				}
			}
		}

		if ($maxLevel >= 1)
		{
			if ($doc instanceof \Rbs\Website\Documents\Section)
			{
				$treeManager = $this->getApplicationServices()->getTreeManager();
				$tn = $treeManager->getNodeByDocument($doc);
				if ($tn)
				{
					foreach ($tn->setTreeManager($treeManager)->getChildren() as $child)
					{
						$entry->addChild($this->getMenuEntry($website, $child->getDocument(), $maxLevel - 1, $currentPage,
							$path));
					}
					if (!$entry->getUrl() && !count($entry->getChildren()))
					{
						return null; // Hide empty topics.
					}
				}
			}
			elseif ($doc instanceof \Rbs\Website\Documents\Menu)
			{
				foreach ($doc->getCurrentLocalization()->getItems() as $item)
				{
					if (isset($item['documentId']))
					{
						$subDoc = $this->getApplicationServices()->getDocumentManager()->getDocumentInstance($item['documentId']);
						$subEntry = $this->getMenuEntry($website, $subDoc, $maxLevel - 1, $currentPage, $path);
						if ($subEntry !== null)
						{
							if (isset($item['titleKey']))
							{
								$subEntry->setTitle($this->i18nManager->trans($item['titleKey'], ['ucf']));
							}
							elseif (isset($item['title']))
							{
								$subEntry->setTitle($item['title']);
							}
							$entry->addChild($subEntry);
						}
					}
					elseif (isset($item['url']) && (isset($item['title']) || isset($item['titleKey'])))
					{
						$subEntry = new \Rbs\Website\Menu\MenuEntry();
						if (isset($item['titleKey']))
						{
							$subEntry->setTitle($this->i18nManager->trans($item['titleKey'], ['ucf']));
						}
						else
						{
							$subEntry->setTitle($item['title']);
						}
						$subEntry->setUrl($item['url']);
						$entry->addChild($subEntry);
					}
				}
			}
		}
		return $entry;
	}

	/**
	 * @param \Change\Documents\AbstractDocument $doc
	 * @return boolean
	 */
	protected function shouldBeDisplayed($doc)
	{
		if ($doc instanceof \Rbs\Website\Documents\Menu)
		{
			return $doc->activated();
		}
		if (!($doc instanceof \Change\Documents\Interfaces\Publishable) || !$doc->published())
		{
			return false;
		}
		if ($doc instanceof \Rbs\Website\Documents\StaticPage && $doc->getHideLinks())
		{
			return false;
		}
		return true;
	}
}