<?php
/**
 * dbdController.php :: dbdController Class File
 *
 * @package dbdMVC
 * @version 1.10
 * @author Don't Blink Design <info@dontblinkdesign.com>
 * @copyright Copyright (c) 2006-2009 by Don't Blink Design
 */

/**
 * Abstract Parent Controller Class
 * All controller classes must extend this class or a child of this class.
 * @todo Use Response object for output.
 * @package dbdMVC
 * @abstract
 * @uses dbdLoader
 * @uses dbdSmarty
 * @uses dbdXajax
 * @uses xajaxResponse
 */
abstract class dbdController
{
	/**
	 * #@+
	 * @access private
	 */
	/**
	 * Application directory
	 * @var string
	 */
	private $app_dir = null;
	/**
	 * Template filename
	 * @var string
	 */
	private $template = null;
	/**
	 * Should dbdController attempt to render
	 * a template on destruction?
	 * @var boolean
	 */
	private $default_render = true;
	/**#@-*/
	/**
	 * #@+
	 * @access protected
	 */
	/**
	 * Instance of dbdRouter
	 * @var dbdRouter
	 */
	protected $router = null;
	/**
	 * Instance of some model.
	 * <b>Note:</b> May not be used.
	 * @var object
	 */
	protected $model = null;
	/**
	 * Instance of dbdSmarty
	 * @var dbdSmarty
	 */
	protected $view = null;
	/**
	 * Instance of dbdXajax
	 * @var dbdXajax
	 */
	protected $xajax = null;
	/**
	 * Instance of xajaxResponse object
	 * @todo make dbdXajaxResponse object
	 * @var dbdXajaxResponse
	 */
	protected $xajaxResponse = null;
	/**#@-*/
	/**
	 * Prepare controller for action.
	 * Set the router and app_dir, create the view, and then call init().
	 * <b>Note:</b> The constructor is declared final to prevent corruption of object.
	 * Any further construction needed by child classes
	 * can be accomplished by overriding dbdController::init().
	 * @final
	 * @param dbdRouter $router
	 * @param string $app_dir
	 */
	final public function __construct(dbdRouter $router, $app_dir)
	{
		$this->app_dir = $app_dir;
		$this->router = $router;
		$this->view = new dbdSmarty($this->app_dir);
		$this->view->assign("app_name", dbdMVC::getAppName());
		$page_class = $this->getController()." ".($this->getAction() ? $this->getAction() : dbdDispatcher::DEFAULT_ACTION);
		$this->view->assign("page_class", $page_class);
		$this->view->assign("page_url", $this->getURL());
		$this->view->assign("page_url_params", dbdURI::replace($this->getURL(true), null, null, array("_" => null)));
		$this->view->assign("this", array(
			"controller" => $this->getController(),
			"action" => $this->getAction()
		));
		if (DBD_MVC_CLI) $this->noRender();
		$this->init();
	}
	/**
	 * Conclude the controller action.
	 * Call dnit().
	 * <b>Note:</b> The destructor is declared final to prevent corruption of object.
	 * Any further destruction needed by child classes
	 * can be accomplished by overriding dbdController::dnit().
	 * @final
	 * @param dbdRouter $router
	 * @param string $app_dir
	 */
	final public function __destruct()
	{
		$this->dnit();
	}
	/**
	 * Auto render the view.
	 * Can be disabled by using $this->noRender();
	 * If a template has not been set, it will attempt to convert
	 * the controller name into a template filename.
	 * @final
	 * @throws dbdException
	 */
	final public function autoRender()
	{
		if (!$this->view->wasRendered() && $this->default_render)
		{
			if (!$this->template)
				$this->template = preg_replace("/^([A-Z]{1})(.*)$/e", "strtolower('$1').$2", $this->getController()).".tpl";
			if (!$this->view->template_exists($this->template))
				throw new dbdException("View (".$this->template.") could not be found!", 404);
			$this->view->display($this->template);
		}
	}
	/**
	 * Auto execute method to be executed after the action.
	 * Can be overridden for additional automatic functionality
	 * (maybe assign some variable to the view).
	 * <b>Note:</b> If overriden, parent::autoExec(); must be
	 * called to maintain current functionality!
	 */
	public function autoExec()
	{
		$this->autoRender();
	}
	/**
	 * Disable auto render on destruction.
	 */
	public function noRender()
	{
		$this->default_render = false;
	}
	/**
	 * Default init method.
	 * This method is to be overridden for additional
	 * object construction and initialization
	 * (maybe setting the $model property).
	 * @access protected
	 */
	protected function init()
	{}
	/**
	 * Default dnit method.
	 * This method is to be overridden for additional
	 * object destruction and de-initialization
	 * (maybe cleaning up the $model property).
	 * @access protected
	 */
	protected function dnit()
	{}
	/**
	 * Default action method.
	 * Can be preformed without the presense of a child controller.
	 */
	public function doDefault()
	{}
	/**
	 * Prepare controller for xajax request.
	 * Ajax actions a called by dbdXajax.
	 * @final
	 */
	final public function doXajax()
	{
		$this->noRender();
		$this->registerXajaxActions();
		$this->xajaxResponse = new xajaxResponse();
		$this->xajax->processRequests();
	}
	/**
	 * #@+
	 * @access protected
	 */
	/**
	 * Register controller actions with dbdXajax.
	 * If controller name is not passed it defaults to the current one.
	 * JavaScript is assigned to view as $xajax.
	 * <b>Note:</b> Acion methods must use the dbdDispatcher::XAJAX_ACTION_PREFIX.
	 * @todo Auto insert JS into header of view output using some filter.
	 * @final
	 * @param string $controller
	 */
	final protected function registerXajaxActions($controller = null)
	{
		if ($controller === null)
			$controller = $this->getController();
		$this->xajax = new dbdXajax($this->router, dbdURI::create($controller, "xajax"), dbdDispatcher::XAJAX_ACTION_PREFIX);
		foreach (get_class_methods($controller) as $m)
		{
			if (preg_match("/^".dbdDispatcher::XAJAX_ACTION_PREFIX."[A-Z]/", $m))
			{
				$m2 = preg_replace("/^".dbdDispatcher::XAJAX_ACTION_PREFIX."([A-Z])/", "$1", $m);
				$this->xajax->registerFunction(array($m2, $this, $m));
			}
		}
		$this->view->assign("xajax", $this->view->get_template_vars("xajax").$this->xajax->getJavascript("/", "dbdXajaxJS/"));
	}
	/**
	 * Set template filename.
	 * @param string $tpl
	 */
	protected function setTemplate($tpl)
	{
		$this->template = $tpl;
	}
	/**
	 * Get template filename.
	 * @return string
	 */
	protected function getTemplate()
	{
		return $this->template;
	}
	/**
	 * Assign all request parameters to the view as an associative array.
	 * <b>Note:</b> Slashes are stripped.
	 */
	protected function assignAllParams()
	{
		$this->view->assign($this->getParams());
	}
	/**
	 * Preform an HTTP Location change.
	 * Disables auto render.
	 * @param string $url
	 */
	protected function forward($url = "/")
	{
		header("Location: ".$url);
		$this->noRender();
	}
	/**
	 * Get application directory.
	 * @return string
	 */
	protected function getAppDir()
	{
		return $this->app_dir;
	}
	/**
	 * Get controller name.
	 * @return string
	 */
	protected function getController()
	{
		return $this->router->getController();
	}
	/**
	 * Get action name.
	 * @return string
	 */
	protected function getAction()
	{
		return $this->router->getAction();
	}
	/**
	 * Set request parameter.
	 * @param string $name
	 * @param mixed value
	 */
	protected function setParam($name, $value)
	{
		return $this->router->setParam($name, $value);
	}
	/**
	 * Unset request parameter.
	 * @param string $name
	 */
	protected function unsetParam($name)
	{
		return $this->router->unsetParam($name);
	}
	/**
	 * Get request parameter.
	 * @param string $name
	 * @return mixed
	 */
	protected function getParam($name)
	{
		return $this->router->getParam($name);
	}
	/**
	 * Get all parameters.
	 * @see dbdRouter::getParams()
	 * @return array
	 */
	protected function getParams()
	{
		return $this->router->getParams();
	}
	/**
	 * Get current request url.
	 * If optional flag is passed, parameters are included.
	 * @param boolean $get_params
	 * @param boolean $host
	 * @return string
	 */
	protected function getURL($get_params = false, $host = false)
	{
		return ($host ? "http://".$this->router->getParam("HTTP_HOST") : "").$this->router->getURL($get_params);
	}
	/**
	 * Get the progress information on a current upload via APC.
	 * @uses php-pecl-apc
	 * @link http://us.php.net/manual/en/apc.configuration.php#ini.apc.rfc1867
	 * @param string $id
	 * @return array
	 */
	protected function getUploadInfo($id)
	{
		if (function_exists("apc_fetch") && ini_get("apc.rfc1867"))
		{
			$a = apc_fetch("upload_".$id);
			if ($a)
			{
				$a['rate'] = $a['current'] / (microtime(true) - $a['start_time']);
				$a['est_sec'] = round(($a['total'] - $a['current']) / $a['rate']);
			}
			return $a;
		}
		else if (function_exists("uploadprogress_get_info"))
		{
			return uploadprogress_get_info($id);
		}
		throw new dbdException("APC not installed or misconfigured! Cannot get upload progress!");
	}
	/**#@-*/
}
?>