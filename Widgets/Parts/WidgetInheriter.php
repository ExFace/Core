<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Mutations\OnMutationsAppliedEvent;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Events\WidgetLinkEventInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Mutations\Prototypes\GenericUxonMutation;

/**
 * Allows a widget to inherit the configuration of another widget.
 * 
 * Specify the `page_alias` only to inherit the configuration of the root widget of the page
 * or add a `widget_id` to inherit from a specific widget. Use jsut a `widget_id` to inherit
 * from a widget on the same page.
 * 
 * **NOTE**: if the inherited widget is located on the same page and has a custom id (i.e.
 * has an `id` property in it's configuration), that id will not be inherited because widget
 * ids must be unique within a page! However, you can explicitly control this behavior via
 * `keep_widget_id`.
 * 
 * ## Overwriting properties of the inherited widget
 * 
 * You can overwrite any widget properties by simply defining them next to `extend_widget` in your
 * widget configuration. However, this will only work well for direct properties, not those of
 * child widgets, etc.
 * 
 * ## Applying mutations
 * 
 * A more flexible way to change the inherited configuration is to use `mutation`. This requires
 * knowledge of the inherited structure, but allows to make well targeted modifications.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetInheriter implements WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    
    private $uxon = null;
    
    private $page = null;
    
    private $widgetLink = null;
    
    private $parentWidget = null;
    
    private $workbench = null;
    
    private $inheritFromPageAlias = null;
    
    private $inheritFromWidgetId = null;
    
    private $keep_widget_id = null;

    private ?UxonObject $mutation = null;

    /**
     * 
     * @param UiPageInterface $page
     * @param UxonObject|string $uxonOrString
     * @param WidgetInterface $parentWidget
     * @throws InvalidArgumentException
     */
    public function __construct(UiPageInterface $page, $uxonOrString, WidgetInterface $parentWidget = null)
    {
        $this->page = $page;
        $this->workbench = $page->getWorkbench();
        $this->parentWidget = $parentWidget;
        
        switch (true) {
            case $uxonOrString instanceof UxonObject:
                $this->uxon = $uxonOrString;
                $this->importUxonObject($uxonOrString);
                break;
            case is_string($uxonOrString):
                $this->widgetLink = WidgetLinkFactory::createFromPage($page, $uxonOrString);
                break;
            default:
                throw new InvalidArgumentException('Invalid widget ');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        if ($this->uxon !== null) {
            return $this->uxon;
        }
        
        $uxon = new UxonObject();
        if ($this->inheritFromPageAlias){
            $uxon->setProperty('page_alias', $this->inheritFromPageAlias);
        }
        if ($this->inheritFromWidgetId){
            $uxon->setProperty('widget_id', $this->inheritFromWidgetId);
        }
        if ($this->keep_widget_id !== null) {
            $uxon->setProperty('keep_widget_id', $this->keep_widget_id);
        }
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @param UxonObject $extendingUxon
     * @return UxonObject
     */
    public function getWidgetUxon(UxonObject $extendingUxon) : UxonObject
    {
        $baseUxon = $this->getInheritFromWidgetLink()->getTargetWidgetUxon();
        if ($baseUxon->isEmpty() && $this->inheritFromPageAlias !== null) {
            try {
                UiPageFactory::createFromModel($this->getWorkbench(), $this->inheritFromPageAlias);
            } catch (UiPageNotFoundError $e) {
                throw new UxonParserError($this->exportUxonObject(), 'Invalid widget inheritance configuration: page "' . $this->inheritFromPageAlias . '" not found!'); 
            }
        }
        // Remove the id from the new widget, because otherwise it would be identical to the id of the widget extended from
        if ($this->getKeepWidgetId() === false) {
            $baseUxon->unsetProperty('id');
        }

        // Extend the linked object by the original one. Thus any properties of the original uxon will override those from the linked widget
        $widgetUxon = $baseUxon->extend($extendingUxon);

        // Apply given mutations to the extending widget
        if ($this->mutation !== null) {
            $mutation = new GenericUxonMutation($this->workbench, $this->mutation);
            $mutation->setName('Widget extension mutation');
            $applied = $mutation->apply($widgetUxon);
            // TODO: pageUxons don't have a name in itself, is there a way to get the page name here?
            $extensionTargetName = $widgetUxon->getProperty('name') ?? $widgetUxon->getProperty('caption');
            $this->getWorkbench()->eventManager()->dispatch(new OnMutationsAppliedEvent([$applied], 'Widget extension mutation for UXON "' . $extensionTargetName . '"'));
        }

        return $widgetUxon;
    }
    
    /**
     * 
     * @return WidgetLinkEventInterface
     */
    protected function getInheritFromWidgetLink() : WidgetLinkInterface
    {
        if ($this->widgetLink === null) {
            $linkUxon = new UxonObject();
            if ($this->inheritFromPageAlias){
                $linkUxon->setProperty('page_alias', $this->inheritFromPageAlias);
            }
            if ($this->inheritFromWidgetId){
                $linkUxon->setProperty('widget_id', $this->inheritFromWidgetId);
            }
            $this->widgetLink = WidgetLinkFactory::createFromPage($this->page, $linkUxon);
        }
        return $this->widgetLink;
    }
    
    /**
     * The selector of the page where the widget to be extended is located
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * 
     * @param string $alias
     * @return WidgetInheriter
     */
    protected function setPageAlias(string $alias) : WidgetInheriter
    {
        $this->inheritFromPageAlias = $alias;
        return $this;
    }
    
    /**
     * The id of the widget to be extended
     * 
     * @uxon-property widget_id
     * @uxon-type string
     * 
     * @param string $id
     * @return WidgetInheriter
     */
    protected function setWidgetId(string $id) : WidgetInheriter
    {
        $this->inheritFromWidgetId = $id;
        return $this;
    }
    
    
    /**
     * Set to TRUE to keep the custom id of the widget to be extended (if it was specified).
     * 
     * If not set explicitly, the behavior will be the following:
     * 
     * - If the inherited widget is located on the same page, its id will not be kept
     * - If no `page_alias` was specified, the inherited widget is assumed to be on the
     * same page, thus it's id is not kept either
     * - Otherwise (i.e. for cross-page inheritance) the id of the extended widget will be
     * kept if it was specified explicitly.
     * 
     * **NOTE:** if the inherited widget does not have an explicitly specified id, the resulting
     * widget will always have a different id!
     * 
     * @uxon-property keep_widget_id
     * @uxon-type boolean
     * 
     * @param bool $trueOrFalse
     * @return WidgetInheriter
     */
    protected function setKeepWidgetId(bool $trueOrFalse) : WidgetInheriter
    {
        $this->keep_widget_id = $trueOrFalse;
        return $this;
    }

    /**
     * @return bool
     */
    protected function getKeepWidgetId() : bool
    {
        if ($this->keep_widget_id === null) {
            switch (true) {
                case $this->getInheritFromWidgetLink()->getTargetPageAlias() === null:
                    return false;
                case $this->page->isExactly($this->getInheritFromWidgetLink()->getTargetPageAlias()):
                    return false;
                default:
                    return true;
            }
        }
        return $this->keep_widget_id;
    }

    /**
     * Add a mutation that will be applied to the new widget after inheriting its uxon from the original widget.
     *
     * @uxon-property add_mutation
     * @uxon-type \exface\Core\Mutations\Prototypes\GenericUxonMutation
     * @uxon-template {"change": {"$.json.path": "value"}}
     *
     * @param UxonObject $mutation Mutation uxon definition
     * @return WidgetInheriter
     */
    public function setAddMutation(UxonObject $mutation): WidgetInheriter
    {
        $this->mutation = $mutation;
        return $this;
    }

    /**
     * @return UxonObject
     */
    public function getMutation(): UxonObject
    {
        return $this->mutation;
    }
}