<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;
use exface\Core\Exceptions\Widgets\WidgetCoordinateUnresolvableError;
use exface\Core\Widgets\DataColumn;

/**
 * Shares data value helpers between Ajax data elements.
 */
trait JsDataValueTrait
{
    /**
     * Accepts column coordinates with numbered rows or standard loaded/selected row scopes.
     *
     * @param WidgetLinkCoordinateInterface $coord
     * @return void
     */
    protected function validateDataValueGetterCoordinate(WidgetLinkCoordinateInterface $coord) : void
    {
        if ($coord->getRowCoordinate() !== null
            && ! $coord->isTargetingRowNumber()
            && $coord->isTargetingSelection() !== true
            && $coord->isTargetingLoadedRows() !== true) {
            throw new WidgetCoordinateUnresolvableError($this->getWidget(), $coord, 'Unsupported row coordinate "' . $coord->getRowCoordinate() . '".');
        }
        if ($coord->isTargetingCustomCoordinate()) {
            throw new WidgetCoordinateUnresolvableError($this->getWidget(), $coord, 'Custom coordinate "' . $coord->getColumnCoordinate() . '" is not supported by this data value getter!');
        }
    }

    /**
     * Aggregates a resolved column from rows selected by the target facade.
     *
     * @param string $rowsJs
     * @param DataColumn $column
     * @param WidgetLinkCoordinateInterface $coord
     * @return string
     */
    protected function buildJsAggregateColumnValues(string $rowsJs, DataColumn $column, WidgetLinkCoordinateInterface $coord) : string
    {
        $nameJs = json_encode($column->getDataColumnName(), JSON_THROW_ON_ERROR);
        $aggregatorJs = json_encode($coord->getAggregator(), JSON_THROW_ON_ERROR);
        $delimiter = $column->isBoundToAttribute() ? $column->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
        $delimiterJs = json_encode($delimiter, JSON_THROW_ON_ERROR);
        return "exfTools.data.aggregateColumnValues({$rowsJs}, {$nameJs}, {$aggregatorJs}, {$delimiterJs})";
    }
}
