<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Widgets\DataToolbar;

/**
 * This trait enables an exception to output meta object specific debug information: properties, attributes, behaviors, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
trait MetaObjectExceptionTrait {
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $meta_object = null;

    public function __construct(Object $meta_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Model\Object
     */
    public function getMetaObject()
    {
        return $this->meta_object;
    }

    /**
     *
     * @param Object $object            
     * @return \exface\Core\Exceptions\Model\MetaObjectExceptionTrait
     */
    public function setMetaObject(Object $object)
    {
        $this->meta_object = $object;
        return $this;
    }

    /**
     * Exceptions for data queries can add extra tabs (e.g.
     * an SQL-tab). Which tabs will be added depends on the implementation of
     * the data query.
     *
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::createDebugWidget()
     *
     * @param
     *            ErrorMessage
     * @return ErrorMessage
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = $this->parentCreateDebugWidget($error_message);
        
        // Do not enrich the debug message widget if DEBUG.SHOW_META_MODEL_DETAILS is false
        if (! $error_message->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_META_MODEL_DETAILS'))
            return $error_message;
        
        /* @var $object_editor \exface\Core\Widgets\Tabs */
        $page = $error_message->getPage();
        $object_object = $page->getWorkbench()->model()->getObject('exface.Core.OBJECT');
        $object_editor = WidgetFactory::createFromUxon($page, $object_object->getDefaultEditorUxon());
        if ($object_editor->is('Tabs')) {
            foreach ($object_editor->getTabs() as $tab) {
                // Skip unimportant tabs
                $skip = false;
                switch ($tab->getCaption()) {
                    case 'Default Editor':
                        $skip = true;
                        break;
                }
                
                if ($skip)
                    continue;
                // Make sure, every tab has the correct meta object (and will not fall back to the parent meta object, which would be
                // the object of the ErrorMessage in this case
                $tab->setMetaObject($tab->getMetaObject());
                
                foreach ($tab->getChildrenRecursive() as $child) {
                    // Remove all buttons, as the ErrorMessage is read-only
                    if ($child instanceof iHaveButtons) {
                        foreach ($child->getButtons() as $button) {
                            $child->removeButton($button);
                        }
                    }
                    // Make sure, no widgets use lazy loading, as it won't work for a widget, that is not part of the page explicitly
                    // for security reasons
                    // TODO many non-lazy tales take a long time to load, so we need to be able to lazy load them somehow. This is
                    // currenlty impossible due to the limitation, that a table can only read data if it is defined in the page, the
                    // request comes from. To get rid of this, we can either identify error widgets somehow and treat them differently
                    // or we need to wait for ABAC to allow reading access based on rules.
                    if ($child instanceof iSupportLazyLoading) {
                        $child->setLazyLoading(false);
                    }
                }
                
                // Add the tab to the error message
                $error_message->addTab($tab);
            }
        }
        
        // Prefill the debug widget with data of the current meta object
        $object_data = DataSheetFactory::createFromObject($object_object);
        $object_data->addFilterFromString($object_object->getUidAlias(), $this->getMetaObject()->getId(), EXF_COMPARATOR_EQUALS);
        $object_data = $error_message->prepareDataSheetToPrefill($object_data);
        $object_data->dataRead();
        $error_message->prefill($object_data);
        
        return $error_message;
    }
}