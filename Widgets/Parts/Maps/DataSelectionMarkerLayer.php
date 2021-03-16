<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class DataSelectionMarkerLayer extends DataMarkersLayer
{
    /**
     * 
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::setData()
     */
    public function setData(UxonObject $uxon) : DataMarkersLayer
    {
        throw new WidgetConfigurationError($this->getMap(), 'Cannot use custom `data` with a "' . $this->getType() . '" layer - use `data_widget_link` instead!');
    }
}