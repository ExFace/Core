<?php
namespace exface\Core\Exceptions\DataSources;

/**
 * Exception thrown if the internal cross-datasource transaction fails to start. 
 *
 * @author Andrej Kabachnik
 *
 */
class DataTransactionStartError extends DataTransactionError {
	public static function get_default_alias(){
		return '6T5VK2M';
	}
}
?>