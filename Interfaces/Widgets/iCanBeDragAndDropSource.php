<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * Interface for widgets and widget parts, that can accept drag&drop items.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanBeDragAndDropSource
{
    /**
     * 
     * @return bool
     */
    public function isDragSource() : bool;
}