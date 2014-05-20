<?php
/**
 * dbdSmarty.php :: dbdSmarty Class File
 *
 * @package dbdMVC
 * @version 1.14
 * @author Don't Blink Design <info@dontblinkdesign.com>
 * @copyright Copyright (c) 2006-2009 by Don't Blink Design
 */

/**
 * Load Smarty core class
 */
dbdLoader::load("Smarty.class.php");
/**
 * dbdMVC Smarty subclass
 * @package dbdMVC
 * @uses dbdMVC
 * @uses Smarty
 * @todo move custom plugins to seperate directory and then register them
 */
class dbdSmarty extends Smarty
{
	/**
	 * Path to directory where smarty templates will live relative to application dir.
	 */
	const VIEW_DIR = "views";
	/**
	 * Path to directory where smarty templates will live relative to view dir.
	 */
	const TEMPLATE_DIR = "templates";
	/**
	 * Path to directory where smarty complied templates will live relative to view dir.
	 */
	const COMPILED_TEMPLATE_DIR = ".templates_c";
	/**
	 * Path to directory where smarty configs will live relative to view dir.
	 */
	const CONFIG_DIR = "configs";
	/**
	 * Path to directory where smarty cache will live relative to view dir.
	 */
	const CACHE_DIR = ".cache";
	/**
	 * Array of regex pairs for minifying the  xhtml.
	 * @var array
	 */
	private $minify_regex = array(
		"printable_tabs" => array("%(?<=[^<>])\t(?=[^<>]*</(textarea|code|pre)>)%i", "&#09;"),
		"printable_newlines" => array("%(?<=[^<>])\r?\n(?=[^<>]*</(textarea|code|pre)>)%i", "&#10;"),
		"printable_whitespace" => array("/(?<=[^<>])(\n|\t)+(?=[^<>])/", " "),
		"spaces" => array("/ [ ]+/", " "),
		"whitespace" => array("/(\n|\t)+/", ""),
		"comments" => array("/<!--.*?-->/", "")
	);
	/**
	 * Array of regex pairs for beautify the xhtml.
	 * @var array
	 */
	private $beautify_regex = array(
		"newlines" => array("/(<[^!\/][^>]*>)(<[^!\/][^>]*>)/", "\\1\n\\2")/*,
		"tabs" => array("/(\n|\t)+/", "")*/
	);
	/**
	 * Flag to designate if the template has been rendered.
	 * @var boolean
	 */
	private $rendered = false;
	/**
	 * Application directory
	 * @var string
	 */
	private $app_dir = null;
	/**
	 * Flag to turn off minification.
	 * @var boolean
	 */
	private $debug = false;
	/**
	 * Flag to turn off minification in debug mode.
	 * @var boolean
	 */
	private $debug_minify = false;
	/**
	 * Css host name if not DOCROOT directory
	 * @var string
	 */
	private $css_host = null;
	/**
	 * List of css files for inclusion
	 * @var array
	 */
	private $css_files = array();
	/**
	 * List of css vars for inclusion
	 * @var array
	 */
	private $css_vars = array();
	/**
	 * JS host name if not DOCROOT directory
	 * @var string
	 */
	private $js_host = null;
	/**
	 * List of js files for inclusion
	 * @var array
	 */
	private $js_files = array();
	/**
	 * List of js vars for inclusion
	 * @var array
	 */
	private $js_vars = array();
	/**
	 * FlashLoader Object for including flash movies.
	 * @var FlashLoaderSWFO
	 */
	private $flash_loader = null;
	/**
	 * QTLoader Object for including quick time movies.
	 * @var QTLoader
	 */
	private $qt_loader = null;
	/**
	 * Smarty contructor that sets implimentation specific variables
	 */
	public function __construct($app_dir)
	{
		parent::Smarty();
		$this->app_dir = $app_dir;
		$this->debug = dbdMVC::debugMode(DBD_DEBUG_HTML);
		$this->debug_minify = dbdMVC::debugMinifyMode();
		$this->setOptions();
	}
	/**
	 * Set Smarty options.
	 * This is where all directories are set, and filters are registered.
	 * @access private
	 */
	private function setOptions()
	{
		$this->template_dir = $this->app_dir.DBD_DS.self::VIEW_DIR.DBD_DS.self::TEMPLATE_DIR.DBD_DS;
		$this->compile_dir  = $this->app_dir.DBD_DS.self::VIEW_DIR.DBD_DS.self::COMPILED_TEMPLATE_DIR.DBD_DS;
		$this->config_dir   = $this->app_dir.DBD_DS.self::VIEW_DIR.DBD_DS.self::CONFIG_DIR.DBD_DS;
		$this->cache_dir    = $this->app_dir.DBD_DS.self::VIEW_DIR.DBD_DS.self::CACHE_DIR.DBD_DS;

		$this->caching = false;
		$this->config_overwrite = false;
		$this->config_booleanize = false;

		$this->plugins_dir[] = DBD_SMARTY_PLUG_DIR;

		$this->register_outputfilter(array($this, "dbdIncludeFiles"));
		if (!$this->debug || $this->debug_minify)
		{
			// commented out minification as this messes up
			// the formatting used in text areas.
			// not a big win any way as we already compressed CSS and JS
//			$this->register_outputfilter(array($this, "dbdMinify"));
		}
		$this->register_outputfilter(array($this, "dbdTag"));
//		$this->register_resource('string', array(
//                                'db_get_template',
//                                'db_get_timestamp',
//                                'db_get_secure',
//                                'db_get_trusted')
//                                );
	}
	/**
	* Executes & displays the template results.
	* Also sets rendered flag.
	* @param string $resource_name
	* @param string $cache_id
	* @param string $compile_id
	*/
	public function display($resource_name, $cache_id = null, $compile_id = null, $no_includes = false)
	{
		$this->rendered = true;
		$this->fetch($resource_name, $cache_id, $compile_id, true, $no_includes);
	}
	/**
	* Executes & returns the template result.
	* @param string $resource_name
	* @param string $cache_id
	* @param string $compile_id
	* @param boolean $display
	* @param boolean $no_includes
	* @param boolean $no_minify
	*/
	public function fetch($resource_name, $cache_id = null, $compile_id = null, $display = false, $no_includes = false, $no_minify = false)
	{
		if ($no_includes)
			$this->unregister_outputfilter("dbdIncludeFiles");
		if ($no_minify)
			$this->unregister_outputfilter("dbdMinify");
		$html = parent::fetch($resource_name, $cache_id, $compile_id, $display);
		if ($no_includes)
			$this->register_outputfilter(array($this, "dbdIncludeFiles"));
		if ($no_minify)
			$this->register_outputfilter(array($this, "dbdMinify"));
		return $html;
	}
	/**
	 * Set CSS host for inclusion
	 * @param string $host
	 */
	public function setCssHost($host)
	{
		$this->css_host = $host;
	}
	/**
	 * Add CSS files to the list
	 * @param mixed $files
	 */
	public function addCSS($files)
	{
		if (!is_array($files))
			$files = explode(',', $files);
		$this->css_files = array_merge($this->css_files, $files);
	}
	/**
	 * Add CSS variable to the list
	 * @param string $name
	 * @param mixed $value
	 */
	public function addCSSVar($name, $value)
	{
		$this->css_vars[$name] = $value;
	}
	/**
	 * Get a CSS variable from the list
	 * @param string $name
	 * @return mixed $value
	 */
	public function getCSSVar($name)
	{
		return key_exists($name, $this->css_vars) ? $this->css_vars[$name] : null;
	}
	/**
	 * Clear CSS file list
	 */
	public function clearCSS()
	{
		$this->css_files = array();
	}
	/**
	 * Set JS host for inclusion
	 * @param string $host
	 */
	public function setJSHost($host)
	{
		$this->js_host = $host;
	}
	/**
	 * Add JS files to the list
	 * @param mixed $files
	 */
	public function addJS($files)
	{
		if (!is_array($files))
			$files = explode(',', $files);
		$this->js_files = array_merge($this->js_files, $files);
	}
	/**
	 * Add JS variable to the list
	 * @param string $name
	 * @return mixed $value
	 */
	public function getJSVar($name)
	{
		return key_exists($name, $this->js_vars) ? $this->js_vars[$name] : null;
	}
	/**
	 * Get a JS variable from the list
	 * @param string $name
	 * @param mixed $value
	 */
	public function addJSVar($name, $value)
	{
		$this->js_vars[$name] = $value;
	}
	/**
	 * Clear JS file list
	 */
	public function clearJS()
	{
		$this->js_files = array();
	}
	/**
	 * Initialize FlashLoaderSWFO
	 * @param mixed
	 */
	public function initFlash()
	{
		if (class_exists("FlashLoaderSWFO", 1))
		{
			if (!$this->flash_loader instanceof FlashLoaderSWFO)
			{
				$this->flash_loader = new FlashLoaderSWFO();
				$this->addJS($this->flash_loader->getCommonJSPath());
			}
			$a = func_get_args();
			call_user_func_array(array($this->flash_loader, 'setAtts'), $a);
		}
		else
		{
			throw new dbdException("FlashLoaderSWFO could not be found!");
		}
	}
	/**
	 * Add a flash movie to FlashLoader
	 * @param mixed
	 */
	public function addFlashMovie()
	{
		$this->initFlash();
		$a = func_get_args();
		call_user_func_array(array($this->flash_loader, 'addMovie'), $a);
	}
	/**
	 * Initialize QTLoader
	 * @param mixed
	 */
	public function initQuickTime()
	{
		if (class_exists("QTLoader", 1))
		{
			if (!$this->qt_loader instanceof QTLoader)
			{
				$this->qt_loader = new QTLoader();
				$this->addJS($this->qt_loader->getCommonJSPath());
			}
			$a = func_get_args();
			call_user_func_array(array($this->qt_loader, 'setAtts'), $a);
		}
		else
		{
			throw new dbdException("QTLoader could not be found!");
		}
	}
	/**
	 * Add a flash movie to FlashLoader
	 * @param mixed
	 */
	public function addQTMovie()
	{
		$this->initQuickTime();
		$a = func_get_args();
		call_user_func_array(array($this->qt_loader, 'addMovie'), $a);
	}
	/**
	 * Has a trmplate been rendered?
	 * @return bollean
	 */
	public function wasRendered()
	{
		return $this->rendered;
	}
	/**
	 * Include CSS & JS Files Automatically
	 * @param string $tpl
	 * @param object $smarty
	 * @return string
	 */
	public function dbdIncludeFiles($tpl, $smarty)
	{
		$css_debug = $this->debug_minify ? $this->debug_minify : dbdMVC::debugMode(DBD_DEBUG_CSS);
		if ($css_debug && count($this->css_files) > 0)
		{
		dbdLog("compiling  css");
			$href = dbdCSS::genURL($this->css_files, $this->css_vars);
			if ($this->css_host !== null)
				$href = "http://".$this->css_host.$href;
			$tag = '<link href="'.$href.'" rel="stylesheet" type="text/css" media="all" />';
			if (preg_match("/<script[^>]+>/", $tpl) && strpos($tpl, "<script") < strpos($tpl, "</head>"))
				$tpl = preg_replace("/(<script[^>]+>)/", $tag."\n\\1", $tpl, 1);
			else
				$tpl = preg_replace("/(<\/head>)/", $tag."\n\\1", $tpl, 1);
		}

		$js_debug = $this->debug_minify ? $this->debug_minify : dbdMVC::debugMode(DBD_DEBUG_JS);
		if ($js_debug && count($this->js_files) > 0)
		{
		dbdLog("compiling  js");
			$int = array();
			$ext = array();
			foreach ($this->js_files as $j)
			{
				if (preg_match("%^https?://%", $j))
					$ext[] = $j;
				else
					$int[] = $j;
			}
			$src = dbdJS::genURL($int, $this->js_vars);
			if ($this->js_host !== null)
				$src = "http://".$this->js_host.$src;
			$tag = "";
			foreach (array_merge(array($src), $ext) as $s)
				$tag .= '<script type="text/javascript" src="'.$s.'"></script>';
			if (preg_match("/<script[^>]+>/", $tpl) && strpos($tpl, "<script") < strpos($tpl, "</head>"))
				$tpl = preg_replace("/(<script[^>]+>)/", $tag."\n\\1", $tpl, 1);
			else
				$tpl = preg_replace("/(<\/head>)/", $tag."\n\\1", $tpl, 1);
		}
		if ($this->flash_loader && $this->flash_loader->hasMovies())
		{
			if (preg_match("/<\/body>/", $tpl))
			{
				$tpl = preg_replace("/(<\/body>)/", $this->flash_loader->getMovies()."\n\\1", $tpl, 1);
			}
			else
			{
				$tpl .= "\n".'<script type="text/javascript" src="'.($this->js_host !== null ? "http://".$this->js_host : "")."/".$this->flash_loader->getCommonJSPath().'"></script>';
				$tpl .= $this->flash_loader->getMovies();
			}
		}
		if ($this->qt_loader && $this->qt_loader->hasMovies())
		{
			if (preg_match("/<\/body>/", $tpl))
			{
				$tpl = preg_replace("/(<\/body>)/", $this->qt_loader->getMovies()."\n\\1", $tpl, 1);
			}
			else
			{
				$tpl .= "\n".'<script type="text/javascript" src="'.($this->js_host !== null ? "http://".$this->js_host : "")."/".$this->qt_loader->getCommonJSPath().'"></script>';
				$tpl .= $this->qt_loader->getMovies();
			}
		}
		return $tpl;
	}
	/**
	 * Beautify buffer.
	 * @param string $tpl
	 * @param object $smarty
	 * @return string
	 */
	public function dbdBeautify($tpl, $smarty)
	{
		foreach ($this->beautify_regex as $k => $v)
			$tpl = preg_replace($v[0], $v[1], $tpl);
		return trim($tpl);
	}


public function protect_characters($array){
    $safe=$array[0];
    $safe=preg_replace('/\\n/', "%%newline%%", $safe);
    $safe=preg_replace('/\\t/', "%%tab%%", $safe);
    $safe=preg_replace('/\\s/', "%%space%%", $safe);
    return $safe;
}

public function unprotect_characters($array){
    $safe=$array[0];
    $safe=preg_replace('/%%newline%%/', "&#13;", $safe);
    $safe=preg_replace('/%%tab%%/', "&#09;", $safe);
    $safe=preg_replace('/%%space%%/', "&#32;", $safe);
    return $safe;
}

	/**
	 * Minify buffer.
	 * @param string $tpl
	 * @param object $smarty
	 * @return string
	 */
	public function dbdMinify($tpl, $smarty)
	{
		$tpl = preg_replace_callback("/>[^<]*<\\/textarea/i", array($this, protect_characters), $tpl);
		foreach ($this->minify_regex as $k => $v)
		{
			$tpl = preg_replace($v[0], $v[1], $tpl);
		}
		$tpl = preg_replace_callback("/>[^<]*<\\/textarea/i", array($this, unprotect_characters), $tpl);

		return trim($tpl);
	}
	/**
	 * Smarty output filter to add dbdMVC credits to source
	 * @param string $tpl
	 * @param object $smarty
	 * @return string
	 */
	public function dbdTag($tpl, $smarty)
	{
		$tag = "<!--".dbdMVC::getAppName()." (".dbdMVC::getExecutionTime().")-->\n";
		if (preg_match("/(<\/html>)/", $tpl))
			$tpl .= $tag;
		return $tpl;
	}
}
?>
