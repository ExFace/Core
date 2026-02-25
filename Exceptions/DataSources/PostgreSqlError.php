<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\PostgreSqlConnector;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Widgets\DebugMessage;
use PgSql\Result;

/**
 * Exception thrown when a PostgreSQL error occurs
 * 
 * This special exception will add a debug tab with PostgreSQL [error details](https://www.postgresql.org/docs/current/libpq-exec.html#LIBPQ-PQRESULTERRORFIELD)
 * if a result resource is provided
 * 
 * However, it will not produce an SQL query tab by itself - wrap it in a DataQueryFailedError or similar instead.
 *
 * @author Andrej Kabachnik
 *        
 */
class PostgreSqlError extends RuntimeException implements DataConnectorExceptionInterface
{
    private PostgreSqlConnector $connector;

    private array $details;

    public function __construct(PostgreSqlConnector $connector, $message, $alias = null, $previous = null, ?Result $res = null)
    {
        $this->connector = $connector;
        $this->details = $res ? $this->getErrorDetails($res) : [];
        parent::__construct($message, $alias, $previous);
    }

    /**
     * Collect diagnostics from a PgSql\Result.
     *
     * Field list comes from pg_result_error_field docs:
     * - https://www.php.net/manual/en/function.pg-result-error-field.php
     * - https://www.postgresql.org/docs/current/libpq-exec.html h - NOTE: need to change prefix "PG_" to "PGSQL_" here
     *
     * @param Result $res
     * @return array
     */
    protected function getErrorDetails(Result $res): array
    {
        return [
            'SQLSTATE'              => pg_result_error_field($res, PGSQL_DIAG_SQLSTATE),
            'SEVERITY_NONLOCALIZED' => pg_result_error_field($res, PGSQL_DIAG_SEVERITY_NONLOCALIZED),
            'MESSAGE_PRIMARY'       => pg_result_error_field($res, PGSQL_DIAG_MESSAGE_PRIMARY),
            'MESSAGE_DETAIL'        => pg_result_error_field($res, PGSQL_DIAG_MESSAGE_DETAIL),
            'MESSAGE_HINT'          => pg_result_error_field($res, PGSQL_DIAG_MESSAGE_HINT),
            'STATEMENT_POSITION'    => pg_result_error_field($res, PGSQL_DIAG_STATEMENT_POSITION),
            'INTERNAL_POSITION'     => pg_result_error_field($res, PGSQL_DIAG_INTERNAL_POSITION),
            'internal_query'        => pg_result_error_field($res, PGSQL_DIAG_INTERNAL_QUERY),
            'INTERNAL_QUERY'        => pg_result_error_field($res, PGSQL_DIAG_CONTEXT),
            'SCHEMA_NAME'           => pg_result_error_field($res, PGSQL_DIAG_SCHEMA_NAME),
            'TABLE_NAME'            => pg_result_error_field($res, PGSQL_DIAG_TABLE_NAME),
            'COLUMN_NAME'           => pg_result_error_field($res, PGSQL_DIAG_COLUMN_NAME),
            'DATATYPE_NAME'         => pg_result_error_field($res, PGSQL_DIAG_DATATYPE_NAME),
            'STATUS_STRING'         => pg_result_status($res, PGSQL_STATUS_STRING)
        ];
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\ExceptionTrait::getDefaultAlias()
     */
    public function getDefaultLogLevel(){
        return LoggerInterface::CRITICAL;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\ExceptionTrait::getDefaultAlias()
     */
    public function getDefaultAlias(){
        return '6T2T2UI';
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugMessage)
    {
        $debugMessage = parent::createDebugWidget($debugMessage);
        if ($this->hasDetails()) {
            $tab = $debugMessage->createTab();
            $tab->setCaption('PostgreSQL');
            $debugMessage->addTab($tab);
            $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
                'widget_type' => 'Markdown',
                'width' => 'max',
                'height' => '100%',
                'hide_caption' => true,
                'value' => $this->toMarkdown(),
            ])));
        }
        return $debugMessage;
    }

    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        $table = MarkdownDataType::buildMarkdownTableFromPropertySet($this->details, 'Error field', 'Value');
        
        $md = <<<MD
The following error details were provided by PostgreSQL - see [official documentation](https://www.postgresql.org/docs/current/libpq-exec.html#LIBPQ-PQRESULTERRORFIELD) for details.

{$table}
MD;
        return $md;
    }

    public function getSqlState() : ?string
    {
        return $this->details['SQLSTATE'] ?? null;
    }

    /**
     * @return string[]
     */
    public function getErrorDetailFields() : array
    {
        return $this->details ?? [];
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::getConnector()
     */
    public function getConnector() : DataConnectionInterface
    {
        return $this->connector;
    }
    
    public function hasDetails() : bool
    {
        return ! empty($this->details);
    }
}