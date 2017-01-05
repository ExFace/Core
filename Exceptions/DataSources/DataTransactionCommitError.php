<?php
namespace exface\Core\Exceptions\DataSources;

/**
 * Exception thrown if the internal cross-datasource transaction fails to commit. This normally indicates,
 * that a commit in one of the affected data sources failed. The respective DataConnectionCommitError will
 * then be attached as the previous exception.
 *
 * @author Andrej Kabachnik
 *
 */
class DataTransactionCommitError extends DataTransactionError {
	public static function get_default_alias(){
		return '6T5VJPV';
	}
}
?>