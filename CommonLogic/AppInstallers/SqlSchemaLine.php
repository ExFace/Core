<?php
namespace exface\Core\CommonLogic\AppInstallers;

/**
 * Represents one normalized SQL schema dump line for schema comparisons.
 */
final class SqlSchemaLine
{
    /**
     * Creates a normalized SQL schema line.
     *
     * @param string $line
     * @param string|null $tableName
     * @param int $lineNumber
     */
    public function __construct(
        private string $line,
        private ?string $tableName,
        private int $lineNumber
    ) {
    }

    /**
     * Returns the normalized SQL line content.
     *
     * @return string
     */
    public function getLine() : string
    {
        return $this->line;
    }

    /**
     * Returns the table name or fallback statement context for this line.
     *
     * @return string|null
     */
    public function getTableName() : ?string
    {
        return $this->tableName;
    }

    /**
     * Returns the original line number in the SQL dump.
     *
     * @return int
     */
    public function getLineNumber() : int
    {
        return $this->lineNumber;
    }

    /**
     * Returns the identifier used to compare schema content.
     *
     * @return string
     */
    public function getComparisonIdentifier() : string
    {
        return ($this->tableName ?? 'Schema root') . "\n" . $this->line;
    }

    /**
     * Returns true if both schema lines describe the same schema content.
     *
     * @param SqlSchemaLine $other
     * @return bool
     */
    public function hasSameSchemaContentAs(SqlSchemaLine $other) : bool
    {
        return $this->getComparisonIdentifier() === $other->getComparisonIdentifier();
    }
}