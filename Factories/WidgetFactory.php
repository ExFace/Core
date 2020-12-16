<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Selectors\WidgetSelector;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Interfaces\Widgets\iCanBeDisabled;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Widgets\Parts\WidgetInheritance;
use exface\Core\Widgets\Parts\WidgetInheriter;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class WidgetFactory extends AbstractStaticFactory
{

    /**
     * Creates a widget of the specified type in the given page.
     * 
     * @param UiPageInterface $page
     * @param string $widget_type
     * @param WidgetInterface $parent_widget
     * 
     * @throws UnexpectedValueException if an unknown widget type is passed
     * 
     * @return WidgetInterface
     */
    public static function create(UiPageInterface $page, $widget_type, WidgetInterface $parent_widget = null)
    {
        if (is_null($widget_type)) {
            throw new UnexpectedValueException('Cannot create widget: widget type could not be deterined!');
        }
        
        /* @var $widget \exface\Core\Widgets\AbstractWidget */
        if (strpos($widget_type, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER) !== false) {
            $selector = new WidgetSelector($page->getWorkbench(), $widget_type);
            $widget = $page->getWorkbench()->getApp($selector->getAppSelector())->get($selector, null, [$page, $parent_widget]);
        } else {
            $widget_class = static::getWidgetClassFromType($widget_type);
            $widget = new $widget_class($page, $parent_widget);
        }
        
        return $widget;
    }

    /**
     * Creates a widget from a UXON description object.
     * The main difference to create_widget() is, that the widget type will be
     * determined from the UXON description. If not given there, the
     * $fallback_widget_type will be used or, if not set, ExFace will attempt
     * to find a default widget type of the meta object or the attribute.
     * 
     * @param UiPageInterface $page
     * @param UxonObject $uxon_object
     * @param WidgetInterface $parent_widget
     * @param string $fallback_widget_type
     * 
     * @throws UxonParserError
     * 
     * @return WidgetInterface
     */
    public static function createFromUxon(UiPageInterface $page, UxonObject $uxon_object, WidgetInterface $parent_widget = null, $fallback_widget_type = null, bool $readonly = false)
    {
        $widget_type = null;
        
        // If the widget is supposed to be extended from another one, merge the uxon descriptions before doing anything else
        if ($uxon_object->hasProperty('extend_widget')) {
            $inheriter = new WidgetInheriter($page, $uxon_object->getProperty('extend_widget'), $parent_widget);
            $uxon_object = $inheriter->getWidgetUxon($uxon_object);
            // Remove the extend widget property to prevent problems when importing UXON
            $uxon_object->unsetProperty('extend_widget');
        }
        
        // See, if the widget type is specified in UXON directly
        if ($uxon_object->hasProperty('widget_type')) {
            $widget_type = $uxon_object->getProperty('widget_type');
            if (! $widget_type) {
                throw new UxonParserError($uxon_object, 'Empty widget_type field in UXON!');
            }
        }
        
        // If not, try to determine it from default widgets
        // IDEA Perhaps, it will be handy to have this logic as a separate method. Not sure though, what it should accept and return...
        if (! $widget_type) {
            // If the UXON does not contain a widget type, use the fallback
            // If there is no fallback, attempt to guess a widget type from the
            // object or attribute the widget should represent.
            if ($fallback_widget_type){
                $widget_type = $fallback_widget_type;
            } else {
                // First of all, we need to figure out, which object the widget is representing
                if ($uxon_object->hasProperty('object_alias')) {
                    $objAlias = $uxon_object->getProperty('object_alias');
                    if (! $objAlias) {
                        throw new UxonParserError($uxon_object, 'Empty object_alias field in UXON!');
                    }
                    $obj = $page->getWorkbench()->model()->getObject($objAlias);
                } elseif ($parent_widget) {
                    $obj = $parent_widget->getMetaObject();
                } else {
                    throw new UxonParserError($uxon_object, 'Cannot find a meta object in UXON widget definition. Please specify an object_alias or a parent widget!');
                }
                // TODO Determine the object via parent_relation_alias, once this field is supported in UXON
                
                // Now, that we have an object, see if the widget should show an attribute. If so, get the default widget for the attribute
                if ($uxon_object->hasProperty('attribute_alias')) {
                    try {
                        $attr = $obj->getAttribute($uxon_object->getProperty('attribute_alias'));
                    } catch (MetaAttributeNotFoundError $e) {
                        throw new UxonParserError($uxon_object, 'Cannot create an editor widget for attribute "' . $uxon_object->getProperty('attribute_alias') . '" of object "' . $obj->getAlias() . '". Attribute not found!', null, $e);
                    }
                    if ($readonly === false) {
                        $uxon_object = $attr->getDefaultEditorUxon()->extend($uxon_object);
                    } else {
                        $uxon_object = $attr->getDefaultDisplayUxon()->extend($uxon_object);
                    }
                    $widget_type = $uxon_object->getProperty('widget_type');
                }
            }
        }
        try {
            $widget = static::create($page, $widget_type, $parent_widget);
            if ($id_space = $uxon_object->getProperty('id_space')) {
                $widget->setIdSpace($id_space);
            }
            if ($id = $uxon_object->getProperty('id')) {
                $widget->setId($id);
            }
        } catch (\Throwable $e) {
            throw new UxonParserError($uxon_object, 'Failed to create a widget from UXON! ' . $e->getMessage(), null, $e);
        }
        
        // Now import the UXON description. Since the import is not an atomic operation, be wure to remove this widget
        // and all it's children if anything goes wrong. This is important, as leaving the broken widget there may
        // produce an inconsistan stage of the application: e.g. the widget is registered in the page, but is not
        // properly referenced in whatever instance had produced it.
        try {
            $widget->importUxonObject($uxon_object);
        } catch (\Throwable $e) {
            try {
                $page->removeWidget($widget, true);
            } finally {
                // Need to throw the error in any case - even if removing failed!
                throw $e;
            }
        }
        
        return $widget;
    }

    protected static function searchForChildWidget(WidgetInterface $haystack, $widget_id)
    {
        if ($haystack->getId() == $widget_id) {
            return $haystack;
        }
        
        foreach ($haystack->getChildren() as $child) {
            if ($found = self::searchForChildWidget($child, $widget_id)) {
                return $found;
            }
        }
        
        // throw new uiWidgetNotFoundException('Cannot find widget "' . $widget_id . '" in container "' . $haystack->getId() . '"!');
        return false;
    }

    /**
     * Returns the qualified class name for the given widget type.
     * 
     * E.g. `\exface\Core\Widgets\DataTable` for `DataTable`.
     * 
     * @param string $widget_type
     * @return string
     */
    public static function getWidgetClassFromType(string $widget_type) : string
    {
        return '\\exface\\Core\\Widgets\\' . ucfirst($widget_type);
    }

    /**
     * 
     * @param UiPage $page
     * @param WidgetInterface|UxonObject $widget_or_uxon_object
     * @param WidgetInterface $parent_widget
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public static function createFromAnything(UiPage $page, $widget_or_uxon_object, WidgetInterface $parent_widget = null)
    {
        if ($widget_or_uxon_object instanceof WidgetInterface) {
            return $widget_or_uxon_object;
        } elseif ($widget_or_uxon_object instanceof UxonObject) {
            return static::createFromUxon($page, UxonObject::fromAnything($widget_or_uxon_object), $parent_widget);
        } else {
            throw new LogicException('Cannot create widget from ' . gettype($widget_or_uxon_object) . ': expecting instance of WidgetInterface or a UxonObject!');
        }
    }
    
    /**
     * 
     * @param WidgetInterface $parent
     * @param UxonObject $uxon
     * @param string $fallbackWidgetType
     * @return WidgetInterface
     */
    public static function createFromUxonInParent(WidgetInterface $parent, UxonObject $uxon, string $fallbackWidgetType = null) : WidgetInterface
    {
        return static::createFromUxon($parent->getPage(), $uxon, $parent, $fallbackWidgetType);
    }
    
    /**
     * Creates the default editor-widget for the given attribute alias of an object.
     * 
     * @param MetaObjectInterface $obj
     * @param string $attribute_alias
     * @param WidgetInterface $parent_widget
     * @return WidgetInterface
     */
    public static function createDefaultEditorForAttributeAlias(MetaObjectInterface $obj, string $attributeAlias, WidgetInterface $parentWidget) : WidgetInterface
    {
        $attr = $obj->getAttribute($attributeAlias);
        $widget = self::createFromUxonInParent($parentWidget, $attr->getDefaultEditorUxon());
        $widget->setAttributeAlias($attributeAlias);
        $widget->setCaption($attr->getName());
        $widget->setHint($attr->getHint());
        return $widget;
    }
    
    /**
     * Creates the default display-widget for the given attribute alias of an object.
     *
     * @param MetaObjectInterface $obj
     * @param string $attribute_alias
     * @param WidgetInterface $parent_widget
     * @return WidgetInterface
     */
    public static function createDefaultDisplayForAttributeAlias(MetaObjectInterface $obj, string $attributeAlias, WidgetInterface $parentWidget) : WidgetInterface
    {
        $attr = $obj->getAttribute($attributeAlias);
        $widget = self::createFromUxonInParent($parentWidget, $attr->getDefaultDisplayUxon());
        $widget->setAttributeAlias($attributeAlias);
        $widget->setCaption($attr->getName());
        $widget->setHint($attr->getHint());
        return $widget;
    }
    
    /**
     * Turns the provided container widget into the default editor for the given meta object.
     * 
     * @param MetaObjectInterface $object
     * @param iContainOtherWidgets $container
     * @return WidgetInterface
     */
    public static function createDefaultEditorForObject(MetaObjectInterface $object, iContainOtherWidgets $container) : WidgetInterface
    {
        $default_editor_uxon = $object->getDefaultEditorUxon();
        if ($default_editor_uxon && false === $default_editor_uxon->isEmpty()) {
            // Otherwise try to generate the widget automatically
            // First check, if there is a default editor for an object, and instantiate it if so
            $default_editor_type = $default_editor_uxon->getProperty('widget_type');
            if (! $default_editor_type || is_a($container, self::getWidgetClassFromType($default_editor_type)) === true) {
                $container->importUxonObject($default_editor_uxon);
                if ($container->isEmpty()) {
                    $container->addWidgets(self::createDefaultEditorsForObjectAttributes($object, $container));
                }
            } elseif ($container->is('Dialog') === false && $default_editor_uxon->hasProperty('widgets') === true) {
                $container->setWidgets($default_editor_uxon->getProperty('widgets'));
            } else {
                $default_editor = self::createFromUxonInParent($container, $default_editor_uxon);
                $container->addWidget($default_editor);
            }
        } else {
            // Lastly, try to generate a usefull editor from the meta model
            $container->addWidgets(self::createDefaultEditorsForObjectAttributes($object, $container));
        }
        
        return $container;
    }
    
    
    
    /**
     * Create default editors for all editable attributes of the object.
     * 
     * @param MetaObjectInterface $object
     * @param iContainOtherWidgets $parent_widget
     * @param bool $onlyEditableAttributes
     * @return WidgetInterface[]
     */
    public static function createDefaultEditorsForObjectAttributes(MetaObjectInterface $object, iContainOtherWidgets $parent_widget, bool $onlyEditableAttributes = true) : array
    {
        $editors = [];
        
        $objectWritable = $object->isWritable();
        
        /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface */
        foreach ($object->getAttributes() as $attr) {
            // Ignore hidden attributes if they are not system attributes
            if ($attr->isHidden()) {
                continue;
            }
            // Ignore not editable attributes if this feature is not explicitly disabled
            if (! $attr->isEditable() && $onlyEditableAttributes) {
                continue;
            }
            // Ignore attributes with fixed values
            if ($attr->getFixedValue()) {
                continue;
            }
            // Create the widget
            $ed = self::createDefaultEditorForAttributeAlias($object, $attr->getAlias(), $parent_widget);
            if ($ed instanceof iCanBeRequired) {
                $ed->setRequired($attr->isRequired());
            }
            if ($ed instanceof iCanBeDisabled) {
                $ed->setDisabled(($attr->isEditable() ? false : true));
            }
            
            if (false === $objectWritable) {
                $ed->setDisabled(true);
            }
            
            $editors[] = $ed;
        }
        
        ksort($editors);
        
        if (empty($editors) === true){
            $editors[] = WidgetFactory::create($parent_widget->getPage(), 'Message', $parent_widget)
            ->setType(MessageTypeDataType::WARNING)
            ->setText($object->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWOBJECTEDITDIALOG.NO_EDITABLE_ATTRIBUTES'));
        }
        
        return $editors;
    }
}
?>