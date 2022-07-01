<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;

/**
 * 
 * 
 * @triggers \exface\Core\Events\Widget\OnWidgetLinkedEvent when created
 *
 * @author Andrej Kabachnik
 *        
 */
interface WidgetLinkInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    const REF_SELF = '~self';
    
    const REF_PARENT = '~parent';
    
    const REF_INPUT = '~input';
    
    /**
     * Returns the page alias of the target page of the link.
     * 
     * @return string
     */
    public function getTargetPageAlias() : ?string;

    /**
     * Returns the target-page of the link.
     * 
     * If the target page is not explicitly specified in the widget link, the current page must be 
     * treated as target.
     * 
     * @return UiPageInterface
     */
    public function getTargetPage() : UiPageInterface;

    /**
     * Returns the id of the linked widget within the linked page.
     * 
     * If an id space is set, this will return the fully qualified widget id including the id space.
     * 
     * @return string
     */
    public function getTargetWidgetId() : string;

    /**
     * Returns the widget instance referenced by this link
     * 
     * @throws WidgetNotFoundError if no widget with a matching id can be found in the specified resource
     * @return WidgetInterface
     */
    public function getTargetWidget() : WidgetInterface;

    /**
     * 
     * @return UxonObject
     */
    public function getTargetWidgetUxon() : UxonObject;

    /**
     * 
     * @return string|NULL
     */
    public function getTargetColumnId() : ?string;

    /**
     * 
     * @return int|NULL
     */
    public function getTargetRowNumber() : ?int;
    
    /**
     * 
     * @return UiPageInterface
     */
    public function getSourcePage() : UiPageInterface;
    
    /**
     * 
     * @return UiPageInterface
     */
    public function getSourceWidget() : WidgetInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasSourceWidget() : bool;
}
?>