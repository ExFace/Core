<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface EditableMapLayerInterface extends MapLayerInterface
{    
    /**
     *
     * @return bool
     */
    public function isEditable() : bool;
    
    /**
     *
     * @return bool
     */
    public function hasEditByAddingItems() : bool;
    
    /**
     *
     * @return int|NULL
     */
    public function hasEditByAddingItemsMax() : ?int;
    
    /**
     *
     * @return bool
     */
    public function hasEditByChangingItems() : bool;
}