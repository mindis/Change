<?php
namespace Change\Application;

/**
 * @name \Change\Application\ApplicationServices
 */
class ApplicationServices extends \Zend\Di\Di
{
	/**
	 * @return \Change\Db\DbProvider
	 */
	public function getDbProvider()
	{
		return $this->get('Change\Db\DbProvider');
	}

	/**
	 * @return \Change\I18n\I18nManager
	 */
	public function getI18nManager()
	{
		return $this->get('Change\I18n\I18nManager');
	}

	/**
	 * @return \Change\Logging\Logging
	 */
	public function getLogging()
	{
		return $this->get('Change\Logging\Logging');
	}


	/**
	 * @return \Change\Transaction\TransactionManager
	 */
	public function getTransactionManager()
	{
		return $this->get('Change\Transaction\TransactionManager');
	}

	/**
	 * @return \Change\Application\PackageManager
	 */
	public function getPackageManager()
	{
		return $this->get('Change\Application\PackageManager');
	}

	/**
	 * @return \Change\Events\EventManager
	 */
	public function getEventManager()
	{
		return $this->get('Change\Events\EventManager');
	}
	
	/**
	 * @return \Change\Db\Query\Builder
	 */
	public function getQueryBuilder()
	{
		return $this->newInstance('Change\Db\Query\Builder', array(), false);
	}
	
	/**
	 * @return \Change\Db\Query\StatementBuilder
	 */
	public function getStatementBuilder()
	{
		return $this->newInstance('Change\Db\Query\StatementBuilder', array(), false);
	}
}