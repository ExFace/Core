<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Widgets\iHaveCaption;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface WidgetInterface extends WorkbenchDependantInterface, iCanBeCopied, iHaveCaption
{

    /**
     * Loads data from a standard object into any widget using setter functions.
     * E.g. calls $this->setId($source->id) for every property of the source object. Thus the behaviour of this
     * function like error handling, input checks, etc. can easily be customized by programming good
     * setters.
     *
     * @param UxonObject $source            
     */
    public function importUxonObject(UxonObject $source);

    /**
     * Returns the UXON description of the widget.
     * If the widget was described by a user, the original description
     * is returned. If the widget was built via API, a description is automatically generated.
     *
     * @return UxonObject
     */
    public function exportUxonObject();

    /**
     * Prefills the widget with values of a data sheet.
     * 
     * Each widget has it's own prefill logic: simple value widgets will just look for
     * a suitable cell value in the data sheet, key-value widgets will try to get
     * both, while complex data widgets may use filters, rows, UIDs, etc.
     * 
     * The prefill process produces three types of events: `OnBeforePrefill` and
     * `OnPrefill` will be fired for every widget, the prefill data is passed to - before 
     * and after processing it - regardless of whether it affetcs a widget or not.
     * `OnPrefillChangeProperty`, on the other hand, will be fired only if the prefill
     * actually affects the widget and will be triggered for every widget property
     * that is changed. This event let's you examine the results of the prefill
     * in detail as it contains old and new values of the affected properties.
     *
     * @triggers \exface\Core\Events\Widget\OnBeforePrefillEvent
     * @triggers \exface\Core\Events\Widget\OnPrefillEvent
     * @triggers \exface\Core\Events\Widget\OnPrefillChangePropertyEvent
     *
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet            
     * @return void
     */
    public function prefill(DataSheetInterface $data_sheet);
    
    /**
     * Returns TRUE if this widget can be prefilled and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isPrefillable();
    
    /**
     * Returns TRUE if the widget was prefilled and FALSE otherwise.
     * 
     * @return bool
     */
    public function isPrefilled() : bool;

    /**
     * Adds attributes, filters, etc.
     * to a given data sheet, so that it can be used to fill the widget with data
     *
     * @param DataSheetInterface $data_sheet            
     * @return DataSheetInterface
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null);

    /**
     * Adds attributes, filters, etc.
     * to a given data sheet, so that it can be used to prefill the widget
     *
     * @param DataSheetInterface $data_sheet            
     * @return DataSheetInterface
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface;

    /**
     * Returns the widget id specified for this widget explicitly (e.g.
     * in the UXON description). Returns NULL if there was no id
     * explicitly specified! Use get_id() instead, if you just need the currently valid widget id.
     *
     * @return string
     */
    public function getIdSpecified();

    /**
     * Returns the widget id generated automatically for this widget.
     * This is not neccesserily the actual widget id - if an id was
     * specified explicitly (e.g. in the UXON description), it will be used instead.
     * Use get_id() instead, if you just need the currently valid widget id.
     *
     * @return string
     */
    public function getIdAutogenerated();

    /**
     * Sets the autogenerated id for this widget
     *
     * @param string $value            
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function setIdAutogenerated($value);

    /**
     * Specifies the id of the widget explicitly, overriding any previos values.
     * The given id must be unique
     * within the page. It will not be modified automatically in any way.
     *
     * @param string $value            
     * @return WidgetInterface
     */
    public function setId($value);

    /**
     * Retruns the id space of this widget.
     *
     * @return string
     */
    public function getIdSpace();

    /**
     * Sets the id space for this widget.
     * This means, all ids, links, etc. of it's children will
     * be resolved within this id space.
     *
     * The id space allows to reuse complex widgets with live references and other links multiple
     * times on a single page. A complex oject editor, for example, can be used by the create,
     * update and dublicate buttons on one page. To make the links within the editor work, each
     * button must have it's own id space.
     *
     * @param string $value            
     * @return WidgetInterface
     */
    public function setIdSpace($value);

    /**
     *
     * @throws WidgetConfigurationError
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMetaObject();

    /**
     * Sets the given object as the new base object for this widget
     *
     * @param MetaObjectInterface $object            
     */
    public function setMetaObject(MetaObjectInterface $object);

    /**
     * Returns the id of this widget
     *
     * @return string
     */
    public function getId();

    /**
     * Returns the widget type (e.g.
     * DataTable)
     *
     * @return string
     */
    public function getWidgetType();

    /**
     *
     * @return boolean
     */
    public function isDisabled();

    /**
     *
     * @param boolean $value            
     */
    public function setDisabled($value);

    /**
     * Returns a dimension object representing the height of the widget.
     *
     * @return WidgetDimension
     */
    public function getWidth();

    /**
     * Sets the width of the widget.
     * The width may be specified in relative ExFace units (in this case, the value is numeric)
     * or in any unit compatible with the current facade (in this case, the value is alphanumeric because the unit must be
     * specified directltly).
     *
     * @param WidgetDimension|string $value            
     * @return WidgetInterface
     */
    public function setWidth($value);

    /**
     * Returns a dimension object representing the height of the widget.
     *
     * @return WidgetDimension
     */
    public function getHeight();

    /**
     * Sets the height of the widget.
     * The height may be specified in relative ExFace units (in this case, the value is numeric)
     * or in any unit compatible with the current facade (in this case, the value is alphanumeric because the unit must be
     * specified directltly).
     *
     * @param WidgetDimension|string $value            
     * @return WidgetInterface
     */
    public function setHeight($value);

    /**
     *
     * @param string $qualified_alias_with_namespace            
     */
    public function setObjectAlias($qualified_alias_with_namespace);

    /**
     * Returns the relation path from the object of the parent widget to the object of this widget.
     * If both widgets are based on the
     * same object or no valid path can be found, an empty path will be returned.
     *
     * @return MetaRelationPathInterface
     */
    public function getObjectRelationPathFromParent();

    /**
     *
     * @param string $string            
     */
    public function setObjectRelationPathFromParent($string);

    /**
     * Returns the relation path from the object of this widget to the object of its parent widget.
     * If both widgets are based on the
     * same object or no valid path can be found, an empty path will be returned.
     *
     * @return MetaRelationPathInterface
     */
    public function getObjectRelationPathToParent();

    /**
     *
     * @param string $string            
     */
    public function setObjectRelationPathToParent($string);

    /**
     * Returns TRUE if the meta object of this widget was not set explicitly but inherited from it's parent and FALSE otherwise.
     *
     * @return boolean
     */
    public function isObjectInheritedFromParent();

    /**
     * Returns the parent widget
     *
     * @return WidgetInterface|null
     */
    public function getParent();
    
    /**
     * Returns TRUE if the widget has a parent and FALSE if it is a root widget.
     * 
     * @return boolean
     */
    public function hasParent();

    /**
     * Sets the parent widget
     *
     * @param WidgetInterface $widget            
     */
    public function setParent(WidgetInterface $widget);

    /**
     *
     * @return string
     */
    public function getHint();

    /**
     *
     * @param string $value            
     */
    public function setHint($value);

    /**
     *
     * @return boolean
     */
    public function isHidden();

    /**
     *
     * @param boolean $value            
     */
    public function setHidden($value);

    /**
     * Returns the current visibility option (one of the EXF_WIDGET_VISIBILITY_xxx constants)
     *
     * @return integer
     */
    public function getVisibility();

    /**
     * Sets visibility of the widget. 
     * 
     * Accepted values are either one of the EXF_WIDGET_VISIBILITY_xxx or the
     * the "xxx" part of the constant name as string: e.g. "normal", "promoted".
     *
     * @param string $value            
     * @throws WidgetPropertyInvalidValueError
     */
    public function setVisibility($value);

    /**
     * Returns the data sheet used to prefill the widget or null if the widget is not prefilled
     *
     * @return DataSheetInterface
     */
    public function getPrefillData();

    /**
     *
     * @param DataSheetInterface $data_sheet            
     */
    public function setPrefillData(DataSheetInterface $data_sheet);

    /**
     * Checks if the widget implements the given interface (e.g.
     * "iHaveButtons"), etc.
     *
     * @param string $interface_name            
     */
    public function implementsInterface($interface_name);

    /**
     * Returns TRUE if the widget is of the given widget type or extends from it and FALSE otherwise
     * (e.g.
     * a DataTable would return TRUE for DataTable and Data)
     *
     * @param string $widget_type            
     * @return boolean
     *
     * @see is_exactly()
     */
    public function is($widget_type);

    /**
     * Returns TRUE if the widget is of the given type and FALSE otherwise.
     * In contrast to is(), it will return FALSE even
     * if the widget extends from the given type.
     *
     * @param string $widget_type            
     * @return boolean
     *
     * @see is()
     */
    public function isExactly($widget_type);

    /**
     * Explicitly tells the widget to use the given data connection to fetch data (instead of the one specified on the base
     * object's data source)
     *
     * @param string $value            
     */
    public function setDataConnectionAlias($value);

    /**
     *
     * @return UiPageInterface
     */
    public function getPage();

    /**
     * Returns the orignal UXON description of this widget specified by the user, that is without any automatic enhancements
     *
     * @return \exface\Core\CommonLogic\UxonObject|\exface\Core\CommonLogic\UxonObject
     */
    public function exportUxonObjectOriginal();
    
    /**
     * Returns TRUE if prefilling is explicitly disabled for this widget and FALSE otherwise (default).
     *
     * @return boolean
     */
    public function getDoNotPrefill();
    
    /**
     * Disable prefilling this widget with input or prefill data, making it allways have the value defined in UXON.
     * 
     * @uxon-property do_not_prefill
     * @uxon-type boolean
     * 
     * @param boolean $true_or_false
     * @return WidgetInterface
     */
    public function setDoNotPrefill($true_or_false);
    
    /**
     * Returns an iterator over all direct children of the current widget.
     *
     * @return WidgetInterface[]
     */
    public function getChildren() : \Iterator;
    
    /**
     * Returns an iterator over all children of the current widget including with their children,
     * childrens children, etc. as a flat array of widgets
     *
     * @return WidgetInterface[]
     */
    public function getChildrenRecursive() : \Iterator;
    
    /**
     * Returns true if current widget has at least one child and FALSE otherwise.
     *
     * @return bool
     */
    public function hasChildren() : bool;
}
?>