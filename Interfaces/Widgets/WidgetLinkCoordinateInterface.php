<?php
namespace exface\Core\Interfaces\Widgets;

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
interface WidgetLinkCoordinateInterface
{
    /**
     * Returns the first coordinate, without interpreting custom widget addresses.
     *
     * @return string|null
     */
    public function getColumnCoordinate() : ?string;

    /**
     * Returns the second coordinate, excluding its aggregator.
     *
     * @return null|string|int
     */
    public function getRowCoordinate() : null|string|int;

    /**
     * Returns the aggregator applied to the addressed rows.
     *
     * @return string|null
     */
    public function getAggregator() : ?string;

    /**
     * Resolves a data column name before an attribute alias, or the default UID column.
     * Explicit UID selectors also resolve columns added lazily by the data widget.
     *
     * @param iHaveColumns $dataWidget
     * @return DataColumn|null
     */
    public function findColumn(iHaveColumns $dataWidget) : ?DataColumn;

    /**
     * Returns whether an explicit numeric row is addressed.
     *
     * @return bool
     */
    public function isTargetingRowNumber() : bool;

    /**
     * Returns NULL when selection binding depends on the widget's evaluation context.
     *
     * @return bool|null
     */
    public function isTargetingSelection() : ?bool;

    /**
     * Returns whether all currently loaded rows are explicitly addressed.
     *
     * Returns NULL when the row scope is not explicit or is widget-specific.
     *
     * @return bool|null
     */
    public function isTargetingLoadedRows() : ?bool;

    /**
     * Returns whether an aggregator was specified.
     *
     * @return bool
     */
    public function hasAggregator() : bool;

    /**
     * Returns whether the first coordinate addresses an ordinary data column.
     *
     * @return bool
     */
    public function isTargetingColumn() : bool;

    /**
     * Returns whether the first coordinate starts with "~".
     *
     * @return bool
     */
    public function isTargetingCustomCoordinate() : bool;

    /**
     * Returns the coordinate in widget-link syntax.
     *
     * @return string
     */
    public function __toString() : string;
}