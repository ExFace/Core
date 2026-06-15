<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\SqlSchemaComparatorInterface;

/**
 * Compares deterministic SQL schema dumps line by line.
 */
class SqlSchemaComparator implements SqlSchemaComparatorInterface
{
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\SqlSchemaComparatorInterface::compare()
     */
    public function compare(string $currentSchema, string $previousSchema) : array
    {
        $diffTree = $this->buildTree($currentSchema, $previousSchema);

        if ($this->treeIsEmpty($diffTree)) {
            return [];
        }

        return $this->buildTreeLines($diffTree);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\SqlSchemaComparatorInterface::buildTree()
     */
    public function buildTree(string $currentSchema, string $previousSchema) : array
    {
        $current = $this->normalizeSchema($currentSchema);
        $previous = $this->normalizeSchema($previousSchema);
        $currentTables = array_fill_keys($current['tables'], true);
        $previousTables = array_fill_keys($previous['tables'], true);

        return [
            'added_tables' => $this->buildTableDifference($current['tables'], $previousTables),
            'removed_tables' => $this->buildTableDifference($previous['tables'], $currentTables),
            'added' => $this->buildDifferenceTree($current['records'], $previous['records'], $previousTables),
            'removed' => $this->buildDifferenceTree($previous['records'], $current['records'], $currentTables),
        ];
    }

    /**
     * Builds a list of table contexts from $from that are missing in $againstTables.
     *
     * @param string[] $from
     * @param array $againstTables
     * @return string[]
     */
    protected function buildTableDifference(array $from, array $againstTables) : array
    {
        $tables = [];
        foreach ($from as $table) {
            if (! isset($againstTables[$table])) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Builds a context grouped tree of records from $from that are missing in $against.
     *
     * @param array $from
     * @param array $against
     * @param array $againstTables
     * @return array
     */
    protected function buildDifferenceTree(array $from, array $against, array $againstTables) : array
    {
        $againstKeys = [];
        foreach ($against as $record) {
            $againstKeys[$record['key']] = true;
        }

        $diffTree = [];
        foreach ($from as $record) {
            if (isset($againstKeys[$record['key']])) {
                continue;
            }
            if ($record['table'] !== null && ! isset($againstTables[$record['table']])) {
                continue;
            }

            $schemaContext = $record['context'];
            if (! isset($diffTree[$schemaContext])) {
                $diffTree[$schemaContext] = [];
            }
            $diffTree[$schemaContext][] = $record['line'];
        }

        return $diffTree;
    }

    /**
     * Builds output lines for a difference tree.
     *
     * @param array $diffTree
     * @return string[]
     */
    protected function buildTreeLines(array $diffTree) : array
    {
        $lines = ['Schema differences'];
        $sections = [];

        if (! empty($diffTree['added_tables'])) {
            $sections[] = ['Added tables', $diffTree['added_tables'], '+ ', 'tables'];
        }
        if (! empty($diffTree['added'])) {
            $sections[] = [$this->buildDifferenceSectionLabel($diffTree['added'], 'Added columns', 'Added in current schema'), $diffTree['added'], '+ ', 'tree'];
        }
        if (! empty($diffTree['removed_tables'])) {
            $sections[] = ['Removed tables', $diffTree['removed_tables'], '- ', 'tables'];
        }
        if (! empty($diffTree['removed'])) {
            $sections[] = [$this->buildDifferenceSectionLabel($diffTree['removed'], 'Removed columns', 'Removed from previous schema'), $diffTree['removed'], '- ', 'tree'];
        }

        $lastSectionIndex = array_key_last($sections);
        foreach ($sections as $sectionIndex => $section) {
            $isLastSection = $sectionIndex === $lastSectionIndex;
            if ($section[3] === 'tables') {
                $this->appendTableSectionLines($lines, $section[0], $section[1], $section[2], $isLastSection);
            } else {
                $this->appendSectionLines($lines, $section[0], $section[1], $section[2], $isLastSection);
            }
        }

        return $lines;
    }

    /**
     * Builds a specific section label if all lines in the tree are column definitions.
     *
     * @param array $sectionTree
     * @param string $columnLabel
     * @param string $fallbackLabel
     * @return string
     */
    protected function buildDifferenceSectionLabel(array $sectionTree, string $columnLabel, string $fallbackLabel) : string
    {
        return $this->treeContainsOnlyColumnLines($sectionTree) ? $columnLabel : $fallbackLabel;
    }

    /**
     * Returns true if every difference line in the tree is a column definition.
     *
     * @param array $sectionTree
     * @return bool
     */
    protected function treeContainsOnlyColumnLines(array $sectionTree) : bool
    {
        foreach ($sectionTree as $diffLines) {
            foreach ($diffLines as $line) {
                if (! $this->isColumnDefinitionLine($line)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns true if the normalized schema line is a column definition.
     *
     * @param string $line
     * @return bool
     */
    protected function isColumnDefinitionLine(string $line) : bool
    {
        return preg_match('/^"[^"]+"\s+/', $line) === 1;
    }

    /**
     * Appends one added/removed table section to the output lines.
     *
     * @param string[] $lines
     * @param string $label
     * @param string[] $tables
     * @param string $linePrefix
     * @param bool $isLastSection
     * @return void
     */
    protected function appendTableSectionLines(array &$lines, string $label, array $tables, string $linePrefix, bool $isLastSection) : void
    {
        $sectionConnector = $isLastSection ? '└── ' : '├── ';
        $sectionIndent = $isLastSection ? '    ' : '│   ';
        $lines[] = $sectionConnector . $label;

        $lastTableIndex = array_key_last($tables);
        foreach ($tables as $tableIndex => $table) {
            $connector = $tableIndex === $lastTableIndex ? '└── ' : '├── ';
            $lines[] = $sectionIndent . $connector . $linePrefix . $table;
        }
    }

    /**
     * Appends one added/removed section to the output lines.
     *
     * @param string[] $lines
     * @param string $label
     * @param array $sectionTree
     * @param string $linePrefix
     * @param bool $isLastSection
     * @return void
     */
    protected function appendSectionLines(array &$lines, string $label, array $sectionTree, string $linePrefix, bool $isLastSection) : void
    {
        $sectionConnector = $isLastSection ? '└── ' : '├── ';
        $sectionIndent = $isLastSection ? '    ' : '│   ';
        $lines[] = $sectionConnector . $label;

        $contexts = array_keys($sectionTree);
        $lastContextIndex = array_key_last($contexts);
        foreach ($contexts as $contextIndex => $context) {
            $isLastContext = $contextIndex === $lastContextIndex;
            $contextConnector = $isLastContext ? '└── ' : '├── ';
            $contextIndent = $isLastContext ? '    ' : '│   ';
            $lines[] = $sectionIndent . $contextConnector . $context;

            $diffLines = $sectionTree[$context];
            $lastLineIndex = array_key_last($diffLines);
            foreach ($diffLines as $index => $line) {
                $connector = $index === $lastLineIndex ? '└── ' : '├── ';
                $lines[] = $sectionIndent . $contextIndent . $connector . $linePrefix . $line;
            }
        }
    }

    /**
     * Normalizes a schema dump into comparable records and table contexts.
     *
     * @param string $schema
     * @return array
     */
    protected function normalizeSchema(string $schema) : array
    {
        $lines = preg_split('/\r\n|\r|\n/', $schema);
        $schemaRecords = [];
        $tables = [];
        $schemaContext = 'Schema root';
        $currentTable = null;

        foreach ($lines as $line) {
            $normalizedLine = trim($line, " \t,");
            if ($normalizedLine === '') {
                continue;
            }

            if ($this->isSchemaStructureLine($normalizedLine)) {
                if ($this->isTableEndLine($normalizedLine)) {
                    $currentTable = null;
                    $schemaContext = 'Schema root';
                }
                continue;
            }

            $createdTable = $this->extractCreateTableName($normalizedLine);
            if ($createdTable !== null) {
                $schemaContext = $createdTable;
                $currentTable = $createdTable;
                $tables[$currentTable] = true;
                continue;
            }

            $statementTable = $this->extractStatementTableContext($normalizedLine);
            if ($statementTable !== null) {
                $schemaContext = $statementTable;
                $currentTable = $statementTable;
                $tables[$currentTable] = true;
            } elseif (! $this->isIndentedLine($line)) {
                $schemaContext = $this->extractGenericStatementContext($normalizedLine);
                $currentTable = null;
            }

            $schemaRecords[] = [
                'line' => $normalizedLine,
                'context' => $schemaContext,
                'table' => $currentTable,
                'key' => $schemaContext . "\n" . $normalizedLine,
            ];
        }

        return [
            'records' => $schemaRecords,
            'tables' => array_keys($tables),
        ];
    }

    /**
     * Returns true if the original schema line starts with whitespace.
     *
     * @param string $line
     * @return bool
     */
    protected function isIndentedLine(string $line) : bool
    {
        return preg_match('/^\s+/', $line) === 1;
    }

    /**
     * Builds a readable fallback context for a top-level SQL statement line.
     *
     * @param string $line
     * @return string
     */
    protected function extractGenericStatementContext(string $line) : string
    {
        if (preg_match('/^CREATE\s+(?:TEMP(?:ORARY)?\s+|UNLOGGED\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(.+?)\s*\($/i', $line, $matches) === 1) {
            return $this->normalizeTableName($matches[1]);
        }

        if (preg_match('/^((?:CREATE|ALTER|DROP)\s+(?:TABLE|VIEW|INDEX|SEQUENCE|TRIGGER|FUNCTION|PROCEDURE))\s+([^\s(]+(?:\s*\.\s*[^\s(]+)?)/i', $line, $matches) === 1) {
            return trim($matches[1] . ' ' . $this->normalizeTableName($matches[2]));
        }

        return $line;
    }

    /**
     * Returns true for normalized lines that only structure a multi-line statement.
     *
     * @param string $line
     * @return bool
     */
    protected function isSchemaStructureLine(string $line) : bool
    {
        return $line === '(' || $line === ')' || $line === ');';
    }

    /**
     * Returns true if a normalized line closes a table or statement block.
     *
     * @param string $line
     * @return bool
     */
    protected function isTableEndLine(string $line) : bool
    {
        return $line === ')' || $line === ');';
    }

    /**
     * Extracts the table name from a CREATE TABLE context line.
     *
     * @param string $line
     * @return string|null
     */
    protected function extractCreateTableName(string $line) : ?string
    {
        if (preg_match('/^CREATE\s+(?:TEMP(?:ORARY)?\s+|UNLOGGED\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(.+?)\s*\($/i', $line, $matches) === 1) {
            return $this->normalizeTableName($matches[1]);
        }

        return null;
    }

    /**
     * Extracts the table context from single-line statements such as ALTER TABLE or CREATE INDEX.
     *
     * @param string $line
     * @return string|null
     */
    protected function extractStatementTableContext(string $line) : ?string
    {
        if (preg_match('/^ALTER\s+TABLE\s+(.+?)\s+(?:ADD|ALTER|DROP|RENAME|ENABLE|DISABLE|OWNER|SET|VALIDATE)\b/i', $line, $matches) === 1) {
            return $this->normalizeTableName($matches[1]);
        }
        if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:(?:IF\s+NOT\s+EXISTS|CONCURRENTLY|[^\s]+)\s+)*ON\s+(.+?)\s*\(/i', $line, $matches) === 1) {
            return $this->normalizeTableName($matches[1]);
        }

        return null;
    }

    /**
     * Normalizes a qualified table name used as context.
     *
     * @param string $tableName
     * @return string
     */
    protected function normalizeTableName(string $tableName) : string
    {
        return trim($tableName);
    }

    /**
     * Returns true if no added or removed lines are present.
     *
     * @param array $diffTree
     * @return bool
     */
    protected function treeIsEmpty(array $diffTree) : bool
    {
        return empty($diffTree['added_tables']) && empty($diffTree['removed_tables']) && empty($diffTree['added']) && empty($diffTree['removed']);
    }
}
