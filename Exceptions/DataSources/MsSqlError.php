<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\MsSqlConnector;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Widgets\DebugMessage;

/**
 * Exception thrown if a data source query fails.
 * It will produce usefull debug information about the query (e.g.
 * a nicely formatted SQL statement for SQL data queries).
 *
 * It is advisable to wrap this exception around any data source specific exceptions to enable the plattform, to
 * understand what's going without having to deal with data source specific exception types.
 *
 * @author Andrej Kabachnik
 *        
 */
class MsSqlError extends RuntimeException implements DataConnectorExceptionInterface
{
    private $connector = null;

    private $sqlErrors = [];

    private $sqlWarnings = [];

    private $sqlState = null;

    private $sqlErrorCode = null;

    private $sqlErrorMessage = null;

    public function __construct(MsSqlConnector $connector, $message, $alias = null, $previous = null)
    {
        $this->connector = $connector;
        $this->sqlErrors = $this->readErrors(SQLSRV_ERR_ERRORS);
        $this->sqlWarnings = $this->readErrors(SQLSRV_ERR_WARNINGS);
        $firstError = $this->sqlErrors[0];
        if (empty($firstError)) {
            $errorMsg = 'Unknown MS SQL error';
        } else {
            $errorMsg = $this->parseSqlError($firstError);
        }
        $message = $message ?? $errorMsg;
        parent::__construct($message, $alias, $previous);
    }

    protected function parseSqlError(array $err) : string
    {
        $this->sqlErrorCode = $err['code'];
        $this->sqlState = $err['SQLSTATE'];
        $msg = $err['message'];
        $this->sqlErrorMessage = $msg;
        // Remove error origin markers like [Microsoft][ODBC Driver Manager]...
        $msg = trim(preg_replace('~^(\[[^]]*])+~m', '', $msg));
        
        // Workaround for strang error in some multi-sequence queries
        if ($msg === 'Function sequence error') {
            $errors = $this->getSqlsrvErrors();
            if (count($errors) > 1) {
                for ($i = 1; $i < count($errors); $i++) {
                    $msg = rtrim($msg, " .") . '. ' . $errors[$i]['message'];
                }
            }
        }
        return $msg;
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
        $tab = $debugMessage->createTab();
        $tab->setCaption('MS SQL Server');
        $debugMessage->addTab($tab);
        $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'Markdown',
            'width' => 'max',
            'height' => '100%',
            'hide_caption' => true,
            'value' => $this->toMarkdown(),
        ])));
        return $debugMessage;
    }

    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        $errors = $this->getSqlsrvErrors();
        if (count($errors) > 0) {
            $errorsTable = MarkdownDataType::buildMarkdownTableFromArray($errors);
        } else {
            $errorsTable = 'No errors provided by MS SQL Server';
        }

        $warnings = $this->getsqlsrvWarnings();
        if (count($warnings) > 0) {
            $warningsTable = MarkdownDataType::buildMarkdownTableFromArray($warnings);
        } else {
            $warningsTable = 'No warnings provided by MS SQL Server';
        }
        return <<<MD
## Main Error

- Message: **{$this->getMessage()}**
- SQL Server error code: `{$this->getSqlErrorCode()}`
- SQL state: `{$this->getSqlState()}`

Helpful links:

- [Microsoft Learn Portal](https://learn.microsoft.com/en-us/sql/relational-databases/errors-events/mssqlserver-{$this->getSqlErrorCode()}-database-engine-error)
- [Search Google](https://www.google.com/search?q=ms+sql+error+{$this->getSqlErrorCode()}+sqlstate+{$this->getSqlState()})

## Connection

- Host: `{$this->getConnector()->getHost()}`
- Database: `{$this->getConnector()->getDatabase()}`

## Error Stack

{$errorsTable}

## Warnings

{$warningsTable}
MD;
    }

    public function getSqlState() : ?string
    {
        return $this->sqlErrorCode;
    }

    public function getSqlErrorCode() : ?string
    {
        return $this->sqlErrorCode;
    }

    public function getSqlErrorMessage() : ?string
    {
        return $this->sqlErrorMessage;
    }

    public function getSqlsrvErrors() : array
    {
        return $this->sqlErrors ?? [];
    }

    public function getSqlsrvWarnings() : array
    {
        return $this->sqlWarnings ?? [];
    }

    /**
     * 
     * @return array{SQLSTATE: mixed, code: mixed, message: mixed[]}
     */
    protected function readErrors(int $errorsOrWarnings = SQLSRV_ERR_ERRORS) : array
    {
        $arr = [];
        foreach (sqlsrv_errors($errorsOrWarnings) as $err) {
            $arr[] = [
                'SQLSTATE' => $err['SQLSTATE'],
                'code' => $err['code'],
                'message' => $err['message']
            ];
        }
        
        return $arr;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::getConnector()
     */
    public function getConnector() : DataConnectionInterface
    {
        return $this->connector;
    }
}