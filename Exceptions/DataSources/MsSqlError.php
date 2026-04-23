<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\MsSqlConnector;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
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
    
    private MsSqlConnector $connector ;

    private $sqlErrors = [];

    private $sqlWarnings = [];

    private $sqlState = null;

    private $sqlErrorCode = null;

    private $sqlErrorMessage = null;

    private ?MetaObjectInterface $obj = null;

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
        $message = $errorMsg ?? $message;
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
- SQL Server error code: `{$this->getSqlErrorCode()}` - [explanation](https://learn.microsoft.com/en-us/sql/relational-databases/errors-events/mssqlserver-{$this->getSqlErrorCode()}-database-engine-error#explanation)
- ODBC error code (SQLSTATE): `{$this->getSqlState()}` - [explanation](https://learn.microsoft.com/en-us/sql/odbc/reference/appendixes/appendix-a-odbc-error-codes)

Helpful links:

- [Microsoft Learn Portal](https://learn.microsoft.com/en-us/sql/relational-databases/errors-events/mssqlserver-{$this->getSqlErrorCode()}-database-engine-error)
- [Search Google](https://www.google.com/search?q=ms+sql+error+{$this->getSqlErrorCode()})

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
        return $this->sqlState;
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
                'message' => $err['message'],
                'code' => $err['code'],
                'SQLSTATE' => $err['SQLSTATE']
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

    public function getAffectedTableName() : ?string
    {
        $msg = $this->getSqlErrorMessage() ?? $this->getMessage();
        if (! $msg) {
            return null;
        }

        $patterns = [
            // Example: The conflict occurred in database "db", table "dbo.users", column 'id'
            '/\btable\s+"([^"]+)"/i',

            // Example: Cannot insert duplicate key row in object 'dbo.test_users'
            "/\bobject\s+'([^']+)'/i",

            // Example: Invalid object name 'dbo.test_users'
            "/\binvalid object name\s+'([^']+)'/i",

            // Fallback if table name is quoted with single quotes
            "/\btable\s+'([^']+)'/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public function getAffectedObject() : ?MetaObjectInterface
    {
        if ($this->obj !== null) {
            return $this->obj;
        }

        $tableName = $this->getAffectedTableName();
        if ($tableName === null) {
            return null;
        }

        // 1. Try full name first, e.g. dbo.test_users
        $this->obj = $this->findObjectByDataAddress($tableName);
        if ($this->obj !== null) {
            return $this->obj;
        }

        // 2. Normalize [dbo].[test_users] => dbo.test_users
        $normalized = str_replace(['[', ']'], '', $tableName);
        if ($normalized !== $tableName) {
            $this->obj = $this->findObjectByDataAddress($normalized);
            if ($this->obj !== null) {
                return $this->obj;
            }
            $tableName = $normalized;
        }

        // 3. Try table name without schema: dbo.test_users => test_users
        if (strpos($tableName, '.') !== false) {
            $parts = explode('.', $tableName);
            $tableOnly = end($parts);

            $this->obj = $this->findObjectByDataAddress($tableOnly);
            if ($this->obj !== null) {
                return $this->obj;
            }
        }

        return null;
    }

    protected function findObjectByDataAddress(string $address) : ?MetaObjectInterface
    {
        $found = null;

        try {
            $objSheet = DataSheetFactory::createFromObjectIdOrAlias(
                $this->getConnector()->getWorkbench(),
                'exface.Core.OBJECT'
            );

            $aliasCol = $objSheet->getColumns()->addFromExpression('ALIAS_WITH_NS');
            $objSheet->getFilters()->addConditionFromString(
                'DATA_ADDRESS',
                $address,
                ComparatorDataType::EQUALS
            );
            $objSheet->dataRead();

            foreach ($aliasCol->getValues() as $alias) {
                $obj = MetaObjectFactory::createFromString(
                    $this->getConnector()->getWorkbench(),
                    $alias
                );

                if ($obj->getDataConnection() === $this->getConnector()) {
                    if ($found !== null) {
                        switch (true) {
                            case $found->isExtendedFrom($obj):
                            case ! $found->isWritable() && $obj->isWritable():
                                break;
                            default:
                                continue 2;
                        }
                    }
                    $found = $obj;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $found;
    }

    public function getAffectedAttributeValues() : array
    {
        $attrAddresses = $this->getAffectedColumnValues();
        $obj = $this->getAffectedObject();

        if (! $obj || empty($attrAddresses)) {
            return [];
        }

        $attrs = [];
        $attrAliases = [];
        $attrVals = [];

        foreach ($attrAddresses as $attrAddress => $val) {
            foreach ($obj->getAttributes() as $attr) {
                if ($attr->getDataAddress() === $attrAddress) {
                    if (array_key_exists($attrAddress, $attrs)) {
                        $prevAttr = $attrs[$attrAddress];
                        switch (true) {
                            case $prevAttr->isLabelForObject():
                            case ! $prevAttr->isWritable() && $attr->isWritable():
                                // current attribute is a better fit
                                break;
                            default:
                                continue 2;
                        }
                    }

                    $attrs[$attrAddress] = $attr;
                    $attrAliases[$attrAddress] = $attr->getAlias();
                    $attrVals[$attrAddress] = $val;
                }
            }
        }

        return array_combine($attrAliases, $attrVals) ?: [];
    }

    public function getAffectedColumnValues() : ?array
    {
        $msg = $this->getSqlErrorMessage() ?? $this->getMessage();
        if (! $msg) {
            return null;
        }

        switch ((int) $this->getSqlErrorCode()) {
            case 515:
                return $this->parseNotNullViolation($msg);

            case 547:
                return $this->parseForeignKeyOrConstraintViolation($msg);

            case 2601:
            case 2627:
                return $this->parseUniqueViolation($msg);

            default:
                return null;
        }
    }

    protected function parseNotNullViolation(string $msg) : ?array
    {
        if (preg_match("/column '([^']+)'/i", $msg, $m)) {
            return [$m[1] => null];
        }

        return null;
    }

    protected function parseForeignKeyOrConstraintViolation(string $msg) : ?array
    {
        if (preg_match("/column '([^']+)'/i", $msg, $m)) {
            return [$m[1] => null];
        }

        return null;
    }

    protected function parseUniqueViolation(string $msg) : ?array
    {
        $duplicateValues = $this->parseDuplicateKeyValues($msg);
        if (empty($duplicateValues)) {
            return null;
        }

        $constraintName = $this->getUniqueConstraintOrIndexName($msg);
        $tableName = $this->getAffectedTableName();
        $columns = [];

        if ($constraintName !== null) {
            $columns = $this->findColumnsByUniqueConstraintName($constraintName);
        }

        if (!empty($columns) && count($columns) === count($duplicateValues)) {
            return array_combine($columns, $duplicateValues);
        }

        if (count($duplicateValues) === 1) {
            $column = $this->guessColumnFromUniqueMessage($msg);
            if ($column !== null) {
                return [$column => reset($duplicateValues)];
            }
        }

        return null;
    }

    protected function getUniqueConstraintOrIndexName(string $msg) : ?string
    {
        $patterns = [
            "/Violation of UNIQUE KEY constraint '([^']+)'/i",
            "/Violation of UNIQUE INDEX constraint '([^']+)'/i",
            "/with unique index '([^']+)'/i",
            "/UNIQUE KEY constraint '([^']+)'/i",
            "/UNIQUE INDEX '([^']+)'/i",
            "/unique index '([^']+)'/i",
            "/unique constraint '([^']+)'/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    protected function findColumnsByUniqueConstraintName(string $name) : array
    {
        try {
            $normalizedName = str_replace(['[', ']'], '', trim($name));

            if(strpos($normalizedName, ";") != false){
                throw new RuntimeException("Column name '" . $normalizedName . "' is not unique");
            }
            
            $sql = "
            SELECT
                col.name AS column_name,
                ic.key_ordinal
            FROM sys.key_constraints c
            INNER JOIN sys.index_columns ic
                ON c.parent_object_id = ic.object_id
               AND c.unique_index_id = ic.index_id
            INNER JOIN sys.columns col
                ON ic.object_id = col.object_id
               AND ic.column_id = col.column_id
            WHERE c.name = '" . $this->connector->escapeString($normalizedName) . "'
              AND ic.key_ordinal > 0
            ORDER BY ic.key_ordinal
        ";

            $rows = $this->getConnector()->runSql($sql)->getResultArray();

            if (!is_array($rows) || empty($rows)) {
                return [];
            }

            $columns = [];
            foreach ($rows as $row) {
                if (isset($row['column_name']) && $row['column_name'] !== '') {
                    $columns[] = $row['column_name'];
                }
            }

            return $columns;
        } catch (\Throwable $e) {
            $this->connector->getWorkbench()->getLogger()->logException($e);
            return [];
        }
    }
    protected function guessColumnFromUniqueMessage(string $msg) : ?string {
        // 1. Regex to find the constraint/index name
        if (preg_match( '/unique (?:index|constraint) ([^ ]+) /i' , $msg, $m)) {
            $name = $m[1]; // Captured constraint name (e.g., "users_email_unique")

            // 2. Heuristic: last underscore-separated token often is the column name
            $parts = explode( '_' , $name);

            if (count($parts) > 1) {
                $candidate = end($parts);

                // 3. Validation: Check if candidate is alphanumeric
                if ($candidate !== false && preg_match( '/^[a-zA-Z][a-zA-Z0-9]*$/' , $candidate)) {
                    return $candidate; // Returns "email" from "users_email_unique"
                }
            }
        }
        return null;
    }


    protected function parseDuplicateKeyValues(string $msg) : array
    {
        if (! preg_match('/duplicate key value is \((.*)\)/i', $msg, $m)) {
            return [];
        }

        return $this->splitMsSqlList($m[1]);
    }

    protected function splitMsSqlList(string $s) : array
    {
        $items = [];
        $buf = '';
        $inQuotes = false;
        $quoteChar = null;

        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];

            if ($inQuotes) {
                $buf .= $ch;
                if ($ch === $quoteChar) {
                    $next = ($i + 1 < $len) ? $s[$i + 1] : null;
                    if ($next === $quoteChar) {
                        $buf .= $next;
                        $i++;
                    } else {
                        $inQuotes = false;
                        $quoteChar = null;
                    }
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inQuotes = true;
                $quoteChar = $ch;
                $buf .= $ch;
                continue;
            }

            if ($ch === ',') {
                $items[] = $this->normalizeMsSqlValue($buf);
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $items[] = $this->normalizeMsSqlValue($buf);
        }

        return $items;
    }

    protected function normalizeMsSqlValue(string $val)
    {
        $val = trim($val);

        if (strcasecmp($val, 'NULL') === 0) {
            return null;
        }

        if (preg_match("/^'(.*)'$/s", $val, $m)) {
            return str_replace("''", "'", $m[1]);
        }

        if (preg_match('/^"(.*)"$/s', $val, $m)) {
            return str_replace('""', '"', $m[1]);
        }

        return $val;
    }
}