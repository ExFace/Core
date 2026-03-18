<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\PostgreSqlConnector;
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
    const SQL_STATE_UNIQUE_VIOLATION = 23505;
    const SQL_STATE_FOREIGN_KEY_VIOLATION = 23503;
    
    private PostgreSqlConnector $connector;
    private string $errorMessage;
    private ?MetaObjectInterface $obj = null;
    
    private array $details;

    public function __construct(PostgreSqlConnector $connector, $message, $alias = null, $previous = null, ?Result $res = null)
    {
        $this->errorMessage = $message;
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

```
{$this->errorMessage}
```

{$table}
MD;
        return $md;
    }

    public function getSqlState() : string|null
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

    /**
     * @return bool
     */
    public function hasDetails() : bool
    {
        return ! empty($this->details);
    }

    /**
     * Returns the fully qualified table name where the error occurred: `<schema>.<table>`
     * @return string|null
     */
    public function getAffectedTableName() : ?string
    {
        if ($this->details['TABLE_NAME']) {
            return ($this->details['SCHEMA_NAME'] ? $this->details['SCHEMA_NAME'] . '.' : '') . $this->details['TABLE_NAME'];
        }
        return null;
    }

    /**
     * Returns an array of column names affected by this error
     * 
     * @return array|null
     */
    public function getAffectedColumns() : ?array
    {
        switch (intval($this->getSqlState())) {
            case self::SQL_STATE_UNIQUE_VIOLATION:
            case self::SQL_STATE_FOREIGN_KEY_VIOLATION:
                return array_keys($this->getAffectedColumnValues());
            default:
                $col = $this->details['COLUMN_NAME'] ?? null;
                return $col === null ? null : [$col];
        }
    }

    /**
     * Returns an array of values that caused the error with column names as array keys
     * 
     * @return array|null
     */
    public function getAffectedColumnValues() : ?array
    {
        switch (intval($this->getSqlState())) {
            case self::SQL_STATE_UNIQUE_VIOLATION:
            case self::SQL_STATE_FOREIGN_KEY_VIOLATION:
                $keyVals = $this->parsePgUniqueViolationKeys($this->details['MESSAGE_DETAIL']);
                return $keyVals;
            default:
                $col = $this->details['COLUMN_NAME'] ?? null;
                return $col === null ? null : [$col];
        }
    }

    /**
     * Returns the metaobject representing the table where the error occurred if available
     * 
     * @return MetaObjectInterface|null
     */
    public function getAffectedObject() : ?MetaObjectInterface
    {
        if ($this->obj === null && null !== $objAddr = $this->getAffectedTableName()) {
            $this->obj = $this->findObjectByDataAddress($objAddr);
            // If no object found for full table name, see if a table name without schema was used
            if ($this->obj === null) {
                $this->obj = $this->findObjectByDataAddress($this->details['TABLE_NAME']);
            }
        }
        return $this->obj;
    }

    /**
     * Returns an array of values that caused the error with attribute aliases as array keys
     * 
     * @return array
     */
    public function getAffectedAttributeValues() : array
    {
        $attrAddresses = $this->getAffectedColumnValues();
        $obj = $this->getAffectedObject();
        if (! $obj || empty($attrAddresses)) {
            return [];
        }
        /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface[] */
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
                                // The current attribute is a better fit
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
        return array_combine($attrAliases, $attrVals);
    }

    /**
     * @param string $address
     * @return MetaObjectInterface|null
     */
    protected function findObjectByDataAddress(string $address) : ?MetaObjectInterface
    {
        $found = null;
        try {
            $objSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getConnector()->getWorkbench(), 'exface.Core.OBJECT');
            $aliasCol = $objSheet->getColumns()->addFromExpression('ALIAS_WITH_NS');
            $objSheet->getFilters()->addConditionFromString('DATA_ADDRESS', $address, ComparatorDataType::EQUALS);
            $objSheet->dataRead();
            foreach ($aliasCol->getValues() as $alias) {
                $obj = MetaObjectFactory::createFromString($this->getConnector()->getWorkbench(), $alias);
                if ($obj->getDataConnection() === $this->getConnector()) {
                    // If we find a second object with the same address, see if it is a better fit
                    if ($found !== null) {
                        switch (true) {
                            // If the previous hit is an extension of this one, keep the new one (base object)
                            case $found->isExtendedFrom($obj):
                            // If the previous hit is not writable, but this one is, take this one as non-writable
                            // objects are typically additions, while writeable ones are the direct representation
                            // of a table.
                            case ! $found->isWritable() && $obj->isWritable():
                                // $obj is a better fit!
                                break;
                            default:
                                continue 2;
                        }
                    }
                    $found = $obj;
                }
            }
        } catch (\Exception $e) {
            return null;
        }
        return $found;
    }

    /**
     * Parse PostgreSQL unique violation detail and return key => value map.
     *
     * Example input:
     *   ERROR: duplicate key value violates unique constraint "article_name_de_owner_ci_unique_index"
     *   DETAIL: Key (owner_id, lower(name_de::text))=(2639, 424c) already exists.
     *
     * Output:
     *   ['owner_id' => '2639', 'name_de' => '424c']
     */
    protected function parsePgUniqueViolationKeys(string $errorText): array
    {
        // Match DETAIL: Key (<cols>)=(<vals>) <anything...>
        // Works for:
        // - unique violations: "... already exists."
        // - foreign key violations: "... is not present in table ..."
        // - delete/update fk violations: "... is still referenced from table ..."
        if (!preg_match('/\s*Key\s*\((.*?)\)\s*=\s*\((.*?)\)\s*(?:$|\r?\n|.*)/si', $errorText, $m)) {
            return [];
        }

        $cols = $this->splitPgList($m[1]);
        $vals = $this->splitPgList($m[2]);

        $n = min(count($cols), count($vals));
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $col = $this->normalizePgKeyColumn($cols[$i]);
            $val = $this->normalizePgKeyValue($vals[$i]);
            $out[$col] = $val;
        }
        return $out;
    }

    /**
     * Split a PostgreSQL comma-separated list where items may contain parentheses
     * or quoted strings. This is safer than explode(',') for expressions like lower(x::text).
     */
    protected function splitPgList(string $s): array
    {
        $items = [];
        $buf = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = null;

        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];

            // Handle quoting (single or double quotes)
            if ($inQuotes) {
                $buf .= $ch;

                // Handle escaped quotes by doubling ('') or ("")
                if ($ch === $quoteChar) {
                    $next = ($i + 1 < $len) ? $s[$i + 1] : null;
                    if ($next === $quoteChar) {
                        // Escaped quote, consume next char too.
                        $buf .= $next;
                        $i++;
                    } else {
                        $inQuotes = false;
                        $quoteChar = null;
                    }
                }
                continue;
            } else {
                if ($ch === "'" || $ch === '"') {
                    $inQuotes = true;
                    $quoteChar = $ch;
                    $buf .= $ch;
                    continue;
                }
            }

            if ($ch === '(') {
                $depth++;
                $buf .= $ch;
                continue;
            }
            if ($ch === ')') {
                if ($depth > 0) $depth--;
                $buf .= $ch;
                continue;
            }

            if ($ch === ',' && $depth === 0) {
                $items[] = trim($buf);
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $items[] = trim($buf);
        }

        return $items;
    }

    /**
     * Normalize column expressions to a usable key name.
     * Examples:
     *   owner_id                   => owner_id
     *   lower(name_de::text)       => name_de
     *   "OwnerId"                  => OwnerId
     *   lower("NameDe"::text)      => NameDe
     *   COALESCE(code,'')          => COALESCE(code,'')  (kept if we can't safely reduce)
     */
    protected function normalizePgKeyColumn(string $colExpr): string
    {
        $colExpr = trim($colExpr);

        // Strip wrapping quotes for identifiers: "owner_id" => owner_id
        // (Preserve case inside quotes.)
        $stripIdentifierQuotes = function (string $x): string {
            $x = trim($x);
            if (preg_match('/^"(.*)"$/s', $x, $mm)) {
                // unescape double quotes inside identifier
                return str_replace('""', '"', $mm[1]);
            }
            return $x;
        };

        // Remove outer function wrappers we know about (e.g., lower(...)).
        // We only special-case lower(...) because it's common in CI indexes.
        if (preg_match('/^\s*lower\s*\((.*)\)\s*$/is', $colExpr, $mm)) {
            $colExpr = trim($mm[1]);
        }

        // Remove PostgreSQL casts: name_de::text => name_de
        $colExpr = preg_replace('/::\s*[a-zA-Z0-9_\."]+\s*$/', '', $colExpr);

        // If expression is still a simple identifier (optionally quoted), return it.
        $candidate = $stripIdentifierQuotes($colExpr);
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $candidate)) {
            return $candidate;
        }

        // Try to extract the first identifier inside parentheses if it's like func(identifier...)
        if (preg_match('/\(\s*("([^"]|"")+"|[a-zA-Z_][a-zA-Z0-9_]*)/s', $colExpr, $mm)) {
            $inner = $stripIdentifierQuotes($mm[1]);
            // Remove any cast on that inner identifier
            $inner = preg_replace('/::\s*[a-zA-Z0-9_\."]+\s*$/', '', $inner);
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $inner)) {
                return $inner;
            }
        }

        // Fallback: return the raw expression (better than losing information).
        return $colExpr;
    }

    /**
     * Normalize values: trim, and if the value is quoted, unquote it.
     * Example:
     *   2639        => "2639"
     *   424c        => "424c"
     *   "A,B"       => A,B  (if it comes quoted)
     *   'hello'     => hello
     *   NULL        => null (as string? choose your preference below)
     */
    protected function normalizePgKeyValue(string $val): string
    {
        $val = trim($val);

        // Convert unquoted NULL literal to empty string or "NULL" as desired.
        // Here: keep "NULL" as-is; comment next line if you want empty string.
        // if (strcasecmp($val, 'NULL') === 0) return '';

        // Unquote single-quoted strings
        if (preg_match("/^'(.*)'$/s", $val, $m)) {
            return str_replace("''", "'", $m[1]);
        }

        // Unquote double-quoted strings (rare in values, but handle)
        if (preg_match('/^"(.*)"$/s', $val, $m)) {
            return str_replace('""', '"', $m[1]);
        }

        return $val;
    }
}