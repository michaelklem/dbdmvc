<?php
/**
 * dbdXajaxJS.php :: dbdXajaxJS Class File
 *
 * @package dbdMVC
 * @version 1.2
 * @author Don't Blink Design <info@dontblinkdesign.com>
 * @copyright Copyright (c) 2006-2009 by Don't Blink Design
 */

/**
 * Controller class for serving xajax javascript file.
 * @package dbdMVC
 * @uses dbdJS
 * @uses dbdException
 */
class dbdXajaxJS extends dbdJS
{
	/**
	 * Serve xajax javascript file
	 * @throws dbdException
	 */
	public function doDefault()
	{
		$this->addFile("xajax_uncompressed.js", DBD_XAJAX_DIR."xajax_js");
		$this->output();
	}
}
?>