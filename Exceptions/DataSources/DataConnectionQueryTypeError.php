<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if an unsupported DataQuery type was passed to the DataConnecto::query() method.
 * 
 * It is best practice for data connectors to always check if a supported query object was passed before 
 * trying to deal with it! Similar query objects could produce strange effects otherwise, that would be
 * hard to debug.
 *
 * @author Andrej Kabachnik
 *
 */
class DataConnectionQueryTypeError extends InvalidArgumentException implements DataConnectorExceptionInterface, ErrorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
	public static function get_default_alias(){
		return '6T5W75J';
	}
	
}
?>