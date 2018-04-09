<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Filter;

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

    public function __construct(MetaObjectInterface $meta_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMetaObject()
    {
        return $this->meta_object;
    }

    /**
     *
     * @param MetaObjectInterface $object            
     * @return \exface\Core\Exceptions\Model\MetaObjectExceptionTrait
     */
    public function setMetaObject(MetaObjectInterface $object)
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
     * @param ErrorMessage $error_message
     * @return ErrorMessage
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = $this->parentCreateDebugWidget($error_message);
        
        // Do not enrich the debug message widget if DEBUG.SHOW_META_MODEL_DETAILS is false
        if (! $error_message->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_META_MODEL_DETAILS')) {
            return $error_message;
        }
        
        $page = $error_message->getPage();
        
        $object_object = $page->getWorkbench()->model()->getObject('exface.Core.OBJECT');
        foreach ($error_message->getTabs() as $tab) {
            if ($tab->getMetaObject()->isExactly($object_object)) {
                return $error_message;
            }
        }
        
        $object_tab = WidgetFactory::create($page, 'Tab', $error_message);
        $object_tab->setMetaObject($this->getMetaObject());
        $object_tab->setCaption($object_tab->translate("ERROR.OBJECT_CAPTION", ["%name%" => $this->getMetaObject()->getName()]));
        $object_tab->setIdSpace($object_tab->getId());
        $error_message->addTab($object_tab);
        
        /* @var $object_editor \exface\Core\Widgets\Tabs */
        $object_editor_descr = $object_object->getDefaultEditorUxon()->toArray();
        // Remove all buttons from the UXON - do it here to make sure, they are not even instantiated as widgets!
        $object_editor_descr = $this->removeKeysFromUxon($object_editor_descr, 'buttons');
        $object_editor = WidgetFactory::createFromUxon($page, new UxonObject($object_editor_descr), $object_tab);
        foreach ($object_editor->getChildrenRecursive() as $child) {
            if ($child instanceof iTakeInput && ! ($child->getParent() instanceof Filter)) {
                $child->setDisabled(true);
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
        $object_tab->addWidget($object_editor);
        
        // Prefill the debug widget with data of the current meta object
        $object_data = DataSheetFactory::createFromObject($object_object);
        $object_data->addFilterFromString($object_object->getUidAttributeAlias(), $this->getMetaObject()->getId(), EXF_COMPARATOR_EQUALS);
        $object_data = $error_message->prepareDataSheetToPrefill($object_data);
        $object_data->dataRead();
        $error_message->prefill($object_data);
        
        return $error_message;
    }
    
    /**
     * 
     * @param array $uxon
     * @param string $keyName
     * @return array
     */
    private function removeKeysFromUxon(array $uxon, string $keyName) : array
    {
        if (empty($uxon)) {
            return $uxon;
        }
        
        $result = [];
        $keyName = strtolower($keyName);
        foreach ($uxon as $key => $value) {
            if (strtolower($key) !== $keyName) {
                if (is_array($value)) {
                    $result[$key] = $this->removeKeysFromUxon($value, $keyName);
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}