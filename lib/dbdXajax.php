<?php
/**
 * dbdXajax.php :: dbdXajax Class File
 *
 * @package dbdMVC
 * @version 1.1
 * @author Don't Blink Design <info@dontblinkdesign.com>
 * @copyright Copyright (c) 2006-2009 by Don't Blink Design
 */

/**
 * Load xajax core class
 */
dbdLoader::load("xajax.inc.php");
/**
 * dbdMVC Xajax subclass
 * @package dbdMVC
 * @uses dbdDispatcher
 * @uses dbdRouter
 * @uses xajax
 */
class dbdXajax extends xajax
{
	/**
	 * Instance of dbdRouter
	 * @access private
	 * @var object
	 */
	private $router = null;
	/**
	 * Set router and construct xajax object
	 * @param dbdRouter $router
	 * @param string $sRequestURI
	 * @param string $sWrapperPrefix
	 * @param string $sEncoding
	 * @param boolean $bDebug
	 */
	public function __construct(dbdRouter $router, $sRequestURI = "", $sWrapperPrefix = dbdDispatcher::XAJAX_ACTION_PREFIX, $sEncoding = XAJAX_DEFAULT_CHAR_ENCODING, $bDebug = false)
	{
		$this->router = $router;
		parent::xajax($sRequestURI, $sWrapperPrefix, $sEncoding, $bDebug);
		$this->exitAllowedOff();
	}
	/**
	 * Wrapper for xajax::_xmlToArray() to set paramaters with dbdRouter
	 * @param string $rootTag
	 * @param string $sXml
	 * @return string
	 */
	public function _xmlToArray($rootTag, $sXml)
	{
		$args = parent::_xmlToArray($rootTag, $sXml);
		if (ini_get("magic_quotes_gpc") == 'On')
			array_walk_recursive($args, create_function('&$v,$k', '$v = addslashes($v);'));
		$this->router->addParam("dbdXajaxArgs", array($args));
		if (is_array($args))
		{
			foreach ($args as $k => $v)
			{
				if (is_string($k) && !is_numeric($k))
				{
					$this->router->setParam($k, $v);
				}
			}
		}
		return $args;
	}
}
?>