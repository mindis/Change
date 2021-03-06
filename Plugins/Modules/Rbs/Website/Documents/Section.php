<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Website\Documents;

use Change\Documents\Events\Event;
use Change\Documents\TreeNode;
use Change\Permissions\PermissionsManager;

/**
 * @name \Rbs\Website\Documents\Section
 */
abstract class Section extends \Compilation\Rbs\Website\Documents\Section implements \Change\Presentation\Interfaces\Section
{
	/**
	 * @var \Rbs\Website\Documents\Page|boolean
	 */
	protected $indexPage = false;

	/**
	 * @var PermissionsManager
	 */
	private $permissionManager;

	/**
	 * @var \Change\Documents\TreeManager
	 */
	private $treeManager;

	/**
	 * @return PermissionsManager|null
	 */
	protected function getPermissionManager()
	{
		return $this->permissionManager;
	}

	/**
	 * @return \Change\Documents\TreeManager|null
	 */
	protected function getTreeManager()
	{
		return $this->treeManager;
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultInjection(\Change\Events\Event $event)
	{
		parent::onDefaultInjection($event);
		$this->permissionManager = $event->getApplicationServices()->getPermissionsManager();
		$this->treeManager = $event->getApplicationServices()->getTreeManager();
	}

	/**
	 * @param \Rbs\Website\Documents\Page $indexPage
	 * @return $this
	 */
	public function setIndexPage($indexPage)
	{
		$this->indexPage = $indexPage;
		return $this;
	}

	/**
	 * @return \Rbs\Website\Documents\Page|null
	 */
	public function getIndexPage()
	{
		if (false === $this->indexPage)
		{
			$query = $this->getDocumentManager()->getNewQuery('Rbs_Website_Page');
			$subQuery = $query->getModelBuilder('Rbs_Website_SectionPageFunction', 'page');
			$subQuery->andPredicates($subQuery->eq('section', $this), $subQuery->eq('functionCode', 'Rbs_Website_Section'));
			$this->indexPage = $query->getFirstDocument();
		}
		return $this->indexPage;
	}

	/**
	 * @return string
	 */
	public function getPathSuffix()
	{
		return '/';
	}

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(Event::EVENT_DISPLAY_PAGE, array($this, 'onDocumentDisplayPage'), 10);
		$eventManager->attach('getPageByFunction', array($this, 'onGetPageByFunction'), 10);
		$eventManager->attach(Event::EVENT_NODE_UPDATED, array($this, 'onNodeUpdated'), 10);
		$eventManager->attach('selectPathRule', array($this, 'onSelectPathRule'), 10);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDocumentDisplayPage(Event $event)
	{
		$document = $event->getDocument();
		if ($document instanceof Section)
		{
			/* @var $pathRule \Change\Http\Web\PathRule */
			$pathRule = $event->getParam("pathRule");
			$parameters = $pathRule->getQueryParameters();
			if (!array_key_exists('sectionPageFunction', $parameters)
				|| $parameters['sectionPageFunction'] == 'Rbs_Website_Section'
			)
			{
				$page = $document->getIndexPage();
				if ($page instanceof \Change\Documents\Interfaces\Publishable && !$page->published())
				{
					return;
				}
				if ($page instanceof FunctionalPage)
				{
					$page->setSection($document);
				}
				$event->setParam('page', $page);
				$event->stopPropagation();
			}
		}
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onGetPageByFunction(Event $event)
	{
		$section = $event->getDocument();
		if ($section instanceof Section)
		{
			$functionCode = $event->getParam('functionCode');
			$treeNode = $event->getApplicationServices()->getTreeManager()->getNodeByDocument($section);
			if ($treeNode)
			{
				$sectionIds = $treeNode->getAncestorIds();
				$sectionIds[] = $section->getId();

				$q = $event->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_Website_SectionPageFunction');
				$q->andPredicates($q->eq('functionCode', $functionCode), $q->in('section', $sectionIds));
				$dbq = $q->dbQueryBuilder();
				$fb = $dbq->getFragmentBuilder();
				$dbq->addColumn($fb->getDocumentColumn('page'))->addColumn($fb->getDocumentColumn('section'));
				$sq = $dbq->query();

				$pageBySections = $sq->getResults($sq->getRowsConverter()->addIntCol('page', 'section')
					->indexBy('section')->singleColumn('page'));
				if (count($pageBySections))
				{
					foreach (array_reverse($sectionIds) as $sectionId)
					{
						if (isset($pageBySections[$sectionId]))
						{
							$page = $this->getDocumentManager()->getDocumentInstance($pageBySections[$sectionId]);
							/* @var $section \Change\Presentation\Interfaces\Section */
							$section = $this->getDocumentManager()->getDocumentInstance($sectionId);

							if ($page instanceof \Rbs\Website\Documents\Page)
							{
								if (!$page->getCurrentLocalization()->isNew())
								{
									if ($page instanceof \Rbs\Website\Documents\StaticPage && !$page->published())
									{
										return;
									}
									elseif ($page instanceof \Rbs\Website\Documents\FunctionalPage)
									{
										$page->setSection($section);
									}

									$event->setParam('page', $page);
									$event->setParam('section', $section);
								}
							}
							return;
						}
					}
				}
			}
		}
	}

	public function onNodeUpdated(Event $event)
	{
		$node = $event->getParam('node');
		if ($node instanceof TreeNode)
		{
			/* @var $section \Rbs\Website\Documents\Section */
			$section = $event->getDocument();
			$permissionManager = $event->getApplicationServices()->getPermissionsManager();

			$accessorIds = $permissionManager->getSectionAccessorIds($node->getParentId(), $section->getWebsite()->getId());
			if (count($accessorIds))
			{
				foreach ($accessorIds as $accessorId)
				{
					$permissionManager->addWebRule($section->getId(), $section->getWebsite()->getId(), $accessorId);
				}
			}
			else
			{
				$permissionManager->addWebRule($section->getId(), $section->getWebsite()->getId());
			}
		}
	}

	/**
	 * @param Event $event
	 */
	public function onSelectPathRule(Event $event)
	{
		$document = $event->getDocument();
		if ($document instanceof Section)
		{
			/* @var $pathRule \Change\Http\Web\PathRule */
			$pathRules = $event->getParam('pathRules');
			$queryParameters = $event->getParam('queryParameters');
			foreach ($pathRules as $pathRule)
			{
				if ($pathRule->getQuery())
				{
					$params = $pathRule->getQueryParameters();
					$found = true;
					foreach ($params as $key => $param)
					{
						if (!isset($queryParameters[$key]) || $queryParameters[$key] != $param)
						{
							$found = false;
							break;
						}
					}
					if ($found)
					{
						foreach ($params as $key => $param)
						{
							unset($queryParameters[$key]);
						}
						$event->setParam('pathRule', $pathRule);
						return;
					}
				}
			}
		}
	}

	/**
	 * @return \Rbs\Website\Documents\Section[]
	 */
	public function getSectionThread()
	{
		return $this->getSectionPath();
	}

	/**
	 * @return \Change\Presentation\Interfaces\Section[]
	 */
	public function getSectionPath()
	{
		$sections = array();
		$tm = $this->getTreeManager();
		if ($tm)
		{
			$tn = $tm->getNodeByDocument($this);
			if ($tn)
			{
				foreach ($tm->getAncestorNodes($tn) as $node)
				{
					$doc = $node->setTreeManager($tm)->getDocument();
					if ($doc instanceof \Rbs\Website\Documents\Section)
					{
						$sections[] = $doc;
					}
				}
			}
		}
		$sections[] = $this;
		return $sections;
	}

	/**
	 * @see \Change\Presentation\Interfaces\Section::getTitle()
	 * @return string
	 */
	public function getTitle()
	{
		return $this->getCurrentLocalization()->isNew() ? $this->getRefLocalization()
			->getTitle() : $this->getCurrentLocalization()->getTitle();
	}

	/**
	 * @see \Change\Presentation\Interfaces\Section::getPathPart()
	 * @return string
	 */
	public function getPathPart()
	{
		return $this->getCurrentLocalization()->getPathPart();
	}

	protected function onUpdate()
	{
		$this->saveAuthorizedAccessors();
	}

	protected function saveAuthorizedAccessors()
	{
		$authorizedUsers = $this->getAuthorizedUsers();
		$authorizedGroups = $this->getAuthorizedGroups();

		$this->getPermissionManager()->deleteWebRules($this->getId(), $this->getWebsite()->getId());
		foreach ($authorizedUsers as $authorizedUser)
		{
			/* @var $authorizedUser \Rbs\User\Documents\User */
			$this->getPermissionManager()->addWebRule($this->getId(), $this->getWebsite()->getId(), $authorizedUser->getId());
		}
		foreach ($authorizedGroups as $authorizedGroup)
		{
			/* @var $authorizedGroup \Rbs\User\Documents\Group */
			$this->getPermissionManager()->addWebRule($this->getId(), $this->getWebsite()->getId(), $authorizedGroup->getId());
		}
		//if no accessor is set, set public access
		if (!count($this->getAuthorizedUsers()) && !count($this->getAuthorizedGroups()))
		{
			$this->getPermissionManager()->addWebRule($this->getId(), $this->getWebsite()->getId());
		}
	}

	//Authorized Users and groups

	/**
	 * @var \Rbs\User\Documents\User[]
	 */
	protected $authorizedUsers = null;

	/**
	 * @var \Rbs\User\Documents\Group[]
	 */
	protected $authorizedGroups = null;

	/**
	 * @return \Rbs\User\Documents\User[]|null
	 */
	public function getAuthorizedUsers()
	{
		if (!is_array($this->authorizedUsers))
		{
			$this->authorizedUsers = $this->getAuthorizedAccessors('Rbs_User_User');
		}
		return $this->authorizedUsers;
	}

	/**
	 * @param \Rbs\User\Documents\User $authorizedUsers
	 * @return $this
	 */
	public function setAuthorizedUsers($authorizedUsers)
	{
		$this->authorizedUsers = $authorizedUsers;
		return $this;
	}

	/**
	 * @return \Rbs\User\Documents\Group[]|null
	 */
	public function getAuthorizedGroups()
	{
		if (!is_array($this->authorizedGroups))
		{
			$this->authorizedGroups = $this->getAuthorizedAccessors('Rbs_User_Group');
		}
		return $this->authorizedGroups;
	}

	/**
	 * @param \Rbs\User\Documents\Group[] $authorizedGroups
	 * @return $this
	 */
	public function setAuthorizedGroups($authorizedGroups)
	{
		$this->authorizedGroups = $authorizedGroups;
		return $this;
	}

	/**
	 * @param string $model null|Rbs_User_User|Rbs_User_Group
	 * @return array
	 */
	protected function getAuthorizedAccessors($model)
	{
		if ($this->isNew())
		{
			return array();
		}
		$accessorIds = $this->getPermissionManager()->getSectionAccessorIds($this->getId(), $this->getWebsite()->getId(), $model);
		$accessors = [];
		foreach ($accessorIds as $accessorId)
		{
			$accessor = $this->getDocumentManager()->getDocumentInstance($accessorId);
			if ($accessor instanceof \Change\Documents\AbstractDocument)
			{
				$accessors[] = $accessor;
			}
		}
		return $accessors;
	}
}