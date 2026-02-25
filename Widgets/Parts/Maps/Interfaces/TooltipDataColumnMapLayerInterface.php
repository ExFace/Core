<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Widgets\DataColumn;

/**
 * Map layers, that show o tooltip from an attribute
 * @author Andrej Kabachnik
 *
 */
interface TooltipDataColumnMapLayerInterface extends MapLayerInterface
{
    /**
     *
     * @return string|NULL
     */
    public function getTooltipAttributeAlias() : ?string;

    /**
     *
     * @return bool
     */
    public function hasTooltip() : bool;

    /**
     *
     * @return DataColumn|NULL
     */
    public function getTooltipColumn() : ?DataColumn;
}