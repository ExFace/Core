<?php
namespace exface\Core\CommonLogic;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;
use exface\Core\Widgets\DataColumn;

/**
 * Addresses a single value within a widget.
 *
 * Different widgets have very different value structures and, consequently, different coordinates for values. Since
 * we are working with data sheets internally, the base coordinate system is based on columns and rows. Widgets may
 * add individual coordinates though.
 *
 * - Simple value widgets (like `Display` and `Input`) do not need coordinates at all - they just have a single value
 * - Data widgets like tables allow links addressing column and rows, and a special `~rowcount` coordinate for the
 * number of currently loaded rows.
 * - The `Scheduler` widget has special coordinates `~start_date`, `~end_date` to allow links to point to its current
 * view boundaries.
 * 
 * The coordinate is an optional part of a widget link, which follows after the widget id: e.g. `myTableWidget!MYCOL$2` 
 * addresses the value in column `MYCOL` and row index 2 of the widget with id `myTableWidget`. This is similar to the
 * cell coordinates in Excel, that are also separated from the sheet name by `!` and by `$` from each-other.
 * 
 * Here are some examples
 * - `MYCOL` addresses the value in this data column (by column name or attribute alias) in the currently active row.
 * Which row is active depends on the widget - it is typically the row behind the selected data item.
 * - `MYCOL$2` addresses the cell in row 2 specifically; row indexes start with zero.
 * - `MYCOL$~loaded:LIST` concatenates values from all loaded rows.
 * - `MYCOL$~selected:LIST` concatenates values from selected rows.
 * - `MYCOL$~loaded:SUM` sums values from all loaded rows.
 * - `MYCOL$~selected:SUM` sums values from selected rows.
 *
 * Since the coordinate resolves to a single value, aggregators must be used when addressing ranges like entire columns. 
 * Supported aggregators are `LIST` (`LIST_ALL` is an alias), `LIST_DISTINCT`, `SUM`, `AVG`,
 * `MIN`, `MAX`, `COUNT` and `COUNT_DISTINCT`. Colons before "$" remain part of the
 * column address, for example `AMOUNT:COUNT$~loaded:SUM`.
 *
 * An omitted row coordinate uses the widget's evaluation context: normally the
 * selection, but potentially the current row inside a spreadsheet. Other custom
 * coordinates, including non-numeric second coordinates, require widget support.
 */
class WidgetLinkCoordinate implements WidgetLinkCoordinateInterface
{
    const COORDINATE_ROWS_LOADED = '~loaded';

    const COORDINATE_ROWS_SELECTED = '~selected';
    
    private ?string $column = null;
    private null|string|int $row = null;
    private ?string $aggregator = null;

    /**
     * Parses the part of a widget link following "!"; an empty string uses widget defaults.
     *
     * @param string $coordinate
     */
    public function __construct(string $coordinate = '')
    {
        // Split only at "$": any aggregator on the column itself belongs to its address.
        $parts = explode('$', trim($coordinate), 2);
        $this->column = $parts[0] === '' ? null : $parts[0];
        if (count($parts) === 1) {
            return;
        }
        if ($parts[1] === '') {
            throw new InvalidArgumentException('Missing row coordinate after "$" in "' . $coordinate . '"!');
        }
        // A colon after "$" separates the row selector from the client-side aggregator.
        $rowParts = explode(':', $parts[1], 2);
        $row = $rowParts[0];
        if ($row === '') {
            throw new InvalidArgumentException('Missing row selector in "' . $coordinate . '"!');
        }
        if (preg_match('/^[0-9]+$/D', $row)) {
            // Normalize leading zeros, but reject indexes that cannot fit in a PHP integer.
            $index = filter_var(ltrim($row, '0') ?: '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($index === false) {
                throw new InvalidArgumentException('Invalid row number in "' . $coordinate . '"!');
            }
            $this->row = $index;
        } else {
            // Preserve scopes and widget-specific row addresses for the target to interpret.
            $this->row = $row;
        }
        if (count($rowParts) === 2) {
            // Accept only aggregators implemented by the shared client-side helper.
            $aggregator = strtoupper($rowParts[1]);
            if (! in_array($aggregator, ['SUM', 'AVG', 'MIN', 'MAX', 'COUNT', 'COUNT_DISTINCT', 'LIST', 'LIST_ALL', 'LIST_DISTINCT'], true)) {
                throw new InvalidArgumentException('Unsupported widget coordinate aggregator "' . $rowParts[1] . '"!');
            }
            $this->aggregator = $aggregator;
        }
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::getColumnCoordinate()
     */
    public function getColumnCoordinate() : ?string
    {
        return $this->column;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::getRowCoordinate()
     */
    public function getRowCoordinate() : null|string|int
    {
        return $this->row;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::getAggregator()
     */
    public function getAggregator() : ?string
    {
        return $this->aggregator;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::findColumn()
     */
    public function findColumn(iHaveColumns $dataWidget) : ?DataColumn
    {
        if ($this->column === null) {
            return $dataWidget->hasUidColumn() ? $dataWidget->getUidColumn() : null;
        }
        if ($this->isTargetingCustomCoordinate()) {
            return null;
        }
        $sanitizedColumnName = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->column);
        $column = $dataWidget->getColumnByDataColumnName($this->column)
            ?? $dataWidget->getColumnByAttributeAlias($this->column)
            ?? $dataWidget->getColumnByDataColumnName($sanitizedColumnName);
        if ($column !== null || ! $dataWidget->hasUidColumn()) {
            return $column;
        }
        // Linked filters can request the UID before the widget has added its lazy UID column.
        $uidColumn = $dataWidget->getUidColumn();
        return $uidColumn !== null
            && ($uidColumn->getDataColumnName() === $this->column
                || $uidColumn->getAttributeAlias() === $this->column
                || $uidColumn->getDataColumnName() === $sanitizedColumnName)
            ? $uidColumn
            : null;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::isTargetingRowNumber()
     */
    public function isTargetingRowNumber() : bool
    {
        return is_int($this->row);
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::isTargetingSelection()
     */
    public function isTargetingSelection() : ?bool
    {
        if ($this->row === self::COORDINATE_ROWS_SELECTED) {
            return true;
        }
        return $this->row === self::COORDINATE_ROWS_LOADED || $this->isTargetingRowNumber() ? false : null;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::isTargetingLoadedRows()
     */
    public function isTargetingLoadedRows() : ?bool
    {
        if ($this->row === self::COORDINATE_ROWS_LOADED) {
            return true;
        }
        return $this->row === self::COORDINATE_ROWS_SELECTED || $this->isTargetingRowNumber() ? false : null;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::hasAggregator()
     */
    public function hasAggregator() : bool
    {
        return $this->aggregator !== null;
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::isTargetingColumn()
     */
    public function isTargetingColumn() : bool
    {
        return $this->column !== null && ! $this->isTargetingCustomCoordinate();
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::isTargetingCustomCoordinate()
     */
    public function isTargetingCustomCoordinate() : bool
    {
        return $this->column !== null && str_starts_with($this->column, '~');
    }

    /**
     * {@inheritDoc}
     * @see WidgetLinkCoordinateInterface::__toString()
     */
    public function __toString() : string
    {
        return ($this->column ?? '')
            . ($this->row === null ? '' : '$' . $this->row)
            . ($this->aggregator === null ? '' : ':' . $this->aggregator);
    }
}