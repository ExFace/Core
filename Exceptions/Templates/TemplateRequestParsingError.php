<?php namespace exface\Core\Exceptions\Templates;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if the template fails to read data from the current HTTP request.
 *
 * @author Andrej Kabachnik
 *
 */
class TemplateRequestParsingError extends RuntimeException {
	
}