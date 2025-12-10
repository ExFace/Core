<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\CommonLogic\QueryBuilder\QueryPartSorter;
use exface\Core\CommonLogic\QueryBuilder\QueryPartValue;
use exface\Core\DataTypes\HexadecimalNumberDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\TextDataType;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;

/**
 * A query builder for PostgreSQL.
 *
 * Supported dialect tags in multi-dialect statements (in order of priority): `@pgSQL:`, `@OTHER:`.
 * 
 * See `AbstractSqlBuilder` for available data address options!
 * 
 * @see AbstractSqlBuilder
 *
 * @author Andrej Kabachnik
 *        
 */
class PostgreSqlBuilder extends MySqlBuilder
{
    const MAX_BUILD_RUNS = 5;
    
    const SQL_DIALECT_PGSQL = 'pgSQL';
    const SQL_DIALECT_POSTGRESQL = 'PostgreSQL';
    
    /**
     *
     * @param QueryBuilderSelectorInterface $selector
     */
    public function __construct(QueryBuilderSelectorInterface $selector)
    {
        parent::__construct($selector);
        // @see https://www.postgresql.org/docs/current/sql-keywords-appendix.html
        $this->setReservedWords(['ALL', 'ANALYSE', 'ANALYZE', 'AND', 'ANY', 'ARRAY', 'AS', 'ASC', 'ASYMMETRIC', 'BOTH', 'CASE', 'CAST', 'CHECK', 'COLLATE', 'COLUMN', 'CONSTRAINT', 'CREATE', 'CURRENT_CATALOG', 'CURRENT_DATE', 'CURRENT_ROLE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'DAY', 'DEFAULT', 'DEFERRABLE', 'DESC', 'DISTINCT', 'DO', 'ELSE', 'END', 'EXCEPT', 'FALSE', 'FETCH', 'FILTER', 'FOR', 'FOREIGN', 'FROM', 'GRANT', 'GROUP', 'HAVING', 'HOUR', 'IN', 'INITIALLY', 'INTERSECT', 'INTO', 'LATERAL', 'LEADING', 'LIMIT', 'LOCALTIME', 'LOCALTIMESTAMP', 'MINUTE', 'MONTH', 'NOT', 'NULL', 'OFFSET', 'ON', 'ONLY', 'OR', 'ORDER', 'OVER', 'PLACING', 'PRIMARY', 'REFERENCES', 'RETURNING', 'SECOND', 'SELECT', 'SESSION_USER', 'SOME', 'SYMMETRIC', 'SYSTEM_USER', 'TABLE', 'THEN', 'TO', 'TRAILING', 'TRUE', 'UNION', 'UNIQUE', 'USER', 'USING', 'VARIADIC', 'VARYING', 'WHEN', 'WHERE', 'WINDOW', 'WITH', 'WITHIN', 'WITHOUT', 'YEAR']);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getSqlDialects()
     */
    protected function getSqlDialects() : array
    {
        return array_merge([self::SQL_DIALECT_PGSQL, self::SQL_DIALECT_POSTGRESQL], parent::getSqlDialects());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::getShortAliasMaxLength()
     */
    protected function getShortAliasMaxLength() : int
    {
        return 63;
    }
    
    /**
     * Returns TRUE if this query can use core/enrichment separation and FALSE otherwise.
     * 
     * Override this method to control enrichment in special constellations.
     * 
     * @return bool
     */
    protected function isEnrichmentAllowed() : bool
    {
        return true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::prepareWhereValue()
     */
    protected function prepareWhereValue($value, DataTypeInterface $data_type, array $dataAddressProps = [])
    {
        /* Date values are wrapped in the ODBC syntax {ts 'value'}. This only should happen
         * if the value is an actual date and not an SQL statement like 'DATE_DIMENSION.date'.
         * Therefor the value is tried to parse as a date in the DateDataType, if that fails the value
         * is treated as an SQL statement. 
         */
        if ($data_type instanceof DateDataType) {
            try {
                $data_type::cast($value);  
                if (null !== $tz = $this->getTimeZoneInSQL($data_type::getTimeZoneDefault($this->getWorkbench()), $this->getTimeZone(), $dataAddressProps[static::DAP_SQL_TIME_ZONE] ?? null)) {
                    $value = $data_type::convertTimeZone($value, $data_type::getTimeZoneDefault($this->getWorkbench()), $tz);
                }
                $output = "'" . $value . "'";
            } catch (DataTypeValidationError $e) {
                $output = $this->escapeString($value);
            }
        } else {
            $output = parent::prepareWhereValue($value, $data_type, $dataAddressProps);
        }
        return $output;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::prepareInputValue()
     */
    protected function prepareInputValue($value, DataTypeInterface $data_type, array $dataAddressProps = [], bool $parse = true)
    {
        $sql_data_type = ($dataAddressProps[static::DAP_SQL_DATA_TYPE] ?? null) === null ? null : mb_strtolower($dataAddressProps[static::DAP_SQL_DATA_TYPE]);
        if ($sql_data_type === static::DAP_SQL_DATA_TYPE_BINARY && $data_type instanceof BinaryDataType) {
            $value = parent::prepareInputValue($value, $data_type, $dataAddressProps, $parse);
            switch ($data_type->getEncoding()) {
                case BinaryDataType::ENCODING_BASE64:
                    return "FROM_BASE64(" . $value . ")";
                case BinaryDataType::ENCODING_BINARY:
                case BinaryDataType::ENCODING_HEX:
                    $value = trim($value, "'");
                    if ($data_type->getEncoding() === BinaryDataType::ENCODING_BINARY) {
                        $value = $data_type->convertToHex($value, true);
                    }
                    if (stripos($value, '0x') === 0) {
                        return "'\x" . substr($value, 2) . "'";
                    } else {
                        return "'$value'";
                    }
                default:
                    throw new QueryBuilderException('Cannot convert value to binary data: invalid encoding "' . $data_type->getEncoding() . '"!');
            }
        } else if ($data_type instanceof TextDataType) {
            if(!($data_type instanceof JsonDataType)) {
                $value = parent::prepareInputValue($value, $data_type, $dataAddressProps, $parse);
                return stripcslashes($value);
            }
        } else if ($data_type instanceof HexadecimalNumberDataType) {
            if ($value === null || $value === '') {
                return 'NULL';
            }
            return "'" . substr($value, 2) . "'";
        }
        
        return parent::prepareInputValue($value, $data_type, $dataAddressProps, $parse);
    }

    /**
     * Returns the SQL to transform the given binary SELECT predicate into something like 0x12433.
     *
     * @param string $select_from
     * @return string
     */
    protected function buildSqlSelectBinaryAsHEX(string $select_from) : string
    {
        // In PostgreSQL casting to `::text` should normalize all letters to lowercase
        return "CONCAT('0x', REPLACE({$select_from}::text, '-', ''))";
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::buildSqlSelectNullCheckFunctionName()
     */
    protected function buildSqlSelectNullCheckFunctionName()
    {
        return 'COALESCE';
    }

    /**
     * @inheritdoc 
     */
    protected function buildSqlJsonEncodeAsFlat(array $keyValuePairs, string $initialJson = "'{}'"): string
    {
        $resultJson = $initialJson;

        foreach ($keyValuePairs as $attributePath => $attributeValue) {
            if ($attributeValue === null || $attributeValue === 'null') {
                $resultJson = "JSON_REMOVE(" . $resultJson . ", '" . $attributePath . "')";
            } else {
                $resultJson = "JSON_SET(" . $resultJson . ", '" . $attributePath . "', " . $attributeValue . ")";
            }
        }

        return $resultJson;
    }

    /**
     * @inheritdoc
     */
    protected function buildSqlJsonInitial(string $columnName): string
    {
        return <<<SQL

CASE 
    WHEN {$columnName} IS NOT NULL AND {$columnName} IS JSON
    THEN {$columnName}
    ELSE '{}'
END 
SQL;
    }

    /**
     * PostgreSQL does not allow table aliases in the SET clause
     * 
     * @see AbstractSqlBuilder::buildSqlSet()
     */
    protected function buildSqlSet(QueryPartValue $qpart, ?string $tableAlias = null, ?string $tableColumn = null) : string
    {
        $tableColumn ??= $this->buildSqlDataAddress($qpart, self::OPERATION_WRITE);
        return $tableColumn;
    }

    /**
     * {@inheritDoc}
     * @see AbstractSqlBuilder::buildSqlAliasForRowCounter()
     */
    protected function buildSqlAliasForRowCounter() : string
    {
        return 'exfcnt';
    }
    
    /**
     * {@inheritDoc}
     * @see AbstractSqlBuilder::decodeBinary()
     */
    protected function decodeBinary($value) : ?string
    {
        if ($value === null) {
            return null;
        }
        $hex = str_replace('-', '', $value);

        // hex → binary data
        return '0x' . $hex;
    }
    
    /**
     * {@inheritDoc}
     * @see AbstractSqlBuilder::buildSqlOrderBy()
     */
    protected function buildSqlOrderBy(QueryPartSorter $qpart, $select_from = '') : string
    {
        $orderByObject = parent::buildSqlOrderBy($qpart, $select_from);
        if ($orderByObject === '') {
            return $orderByObject;
        }
        $parts = preg_split('/\s+/', $orderByObject, 2);
        $expr  = $parts[0];
        $direction   = isset($parts[1]) ? strtoupper(trim($parts[1])) : '';

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $expr = $orderByObject;
            $direction  = '';
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expr)) {
            $expr = '"' . str_replace('"', '""', $expr) . '"';
        }
        return trim($expr . ' ' . $direction);
    }
    
    /**
     * {@inheritDoc}
     * @see AbstractSqlBuilder::buildSqlGroupByExpression()
     */
    protected function buildSqlGroupByExpression(QueryPartAttribute $qpart, $sql, AggregatorInterface $aggregator){
        $output = '';

        $args = $aggregator->getArguments();
        $function_name = $aggregator->getFunction()->getValue();

        switch ($function_name) {
            case AggregatorFunctionsDataType::SUM:
            case AggregatorFunctionsDataType::AVG:
            case AggregatorFunctionsDataType::COUNT:
            case AggregatorFunctionsDataType::MAX:
            case AggregatorFunctionsDataType::MIN:
                $output = $function_name . '(' . $sql . ')';
                break;
            case AggregatorFunctionsDataType::MAX_OF:
            case AggregatorFunctionsDataType::MIN_OF:
                // MIN_OF/MAX_OF is handled in buildSqlSelectSubselect()
                $output = $sql;
                break;
            case AggregatorFunctionsDataType::LIST_DISTINCT:
            case AggregatorFunctionsDataType::LIST_ALL:
                $delim = $args[0] ?? $this->buildSqlGroupByListDelimiter($qpart);
                $output = "STRING_AGG(" . ($function_name == 'LIST_DISTINCT' ? 'DISTINCT ' : '') . $sql . ", '{$this->escapeString($delim)}')";
                $qpart->getQuery()->addAggregation($qpart->getAttribute()->getAliasWithRelationPath());
                break;
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                $output = "COUNT(DISTINCT " . $sql . ")";
                break;
            case AggregatorFunctionsDataType::COUNT_IF:
                $cond = $args[0];
                list($if_comp, $if_val) = explode(' ', $cond ?? '', 2);
                if (!$if_comp || is_null($if_val)) {
                    throw new QueryBuilderException('Invalid argument for COUNT_IF aggregator: "' . $cond . '"!', '6WXNHMN');
                }
                //we have to explicitly use the datatype of the attribute here so we can parte the values correctly in the where part
                $output = "SUM(CASE WHEN " . $this->buildSqlWhereComparator($qpart, $sql, $if_comp, $if_val, false, false, $qpart->getAttribute()->getDataType()) . " THEN 1 ELSE 0 END)";
                break;
            default:
                break;
        }

        return $output;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::escapeAlias()
     */
    protected function escapeAlias(string $tableOrPredicateAlias) : string
    {
        return '"' . $tableOrPredicateAlias . '"';
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\AbstractSqlBuilder::escapeString()
     */
    protected function escapeString($string)
    {
        /* This did not work because it escaped `'` and `\` with backslashes.
         * It is also not clear if excaping \x0A (= \n) and \x0D (= \r) is needed.
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[\x00\x0A\x0D\x1A\x27\x5C]', '\\\0', $string);
        } else {
            return preg_replace('~[\x00\x0A\x0D\x1A\x27\x5C]~u', '\\\$0', $string);
        }*/

        if ($string === null || $string === ''){
            return '';
        }
        if (is_numeric($string)) return $string;

        // Remove invisible ASCII control chars like \x00 (NUL), etc.
        $toRemove = [
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        ];
        foreach ($toRemove as $regex) {
            $string = preg_replace($regex, '', $string );
        }
        // Escape single quotes with another single quote
        $string = str_replace("'", "''", $string );

        return $string;
    }
}