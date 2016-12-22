<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

class DataConnectionQueryTypeError extends InvalidArgumentException implements DataConnectorExceptionInterface, ErrorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
	public static function get_default_code(){
		return '6T5W75J';
	}
	
}
?>