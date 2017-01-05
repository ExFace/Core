<?php
namespace exface\Core\Exceptions\DataSources;

/**
 * Exception thrown the commit fails in a data source.
 * 
 * It is advisable to wrap this exception around any data source specific exceptions to enable the plattform, to
 * understand what's going without having to deal with data source specific exception types.
 *
 * @author Andrej Kabachnik
 *
 */
class DataConnectionCommitFailedError extends DataConnectorError {

}
?>