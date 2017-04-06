<?php
namespace exface\Core\Exceptions;

/**
 * Exception thrown if a UI page could not be loaded, although it's id is known to the CMS.
 * 
 * This can be due to some CMS errors or if the page contents cannot be parsed or the widget configuration is invalid.
 * This exception is mostly used as a wrapper for more specific errors.
 *
 * @author Andrej Kabachnik
 *
 */
class UiPageLoadingError extends RuntimeException {
	
}
?>