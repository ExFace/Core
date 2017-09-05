<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\WidgetDimensionFactory;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\Widgets\WidgetIdConflictError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Widgets\WidgetPropertyUnknownError;
use exface\Core\Factories\EventFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Exceptions\Widgets\WidgetHasNoMetaObjectError;
use exface\Core\Factories\WidgetFactory;

/**
 * Basic ExFace widget
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractWidget implements WidgetInterface, iHaveChildren
{
    use ImportUxonObjectTrait {
		importUxonObject as importUxonObjectDefault;
	}

    private $id_specified = null;

    private $id_autogenerated = null;

    private $caption = null;

    private $hint = null;

    private $widget_type = null;

    private $meta_object_id = null;

    private $object_alias = null;

    private $object_relation_path_to_parent = null;

    private $object_relation_path_from_parent = null;

    private $object_qualified_alias = null;

    private $value = null;

    private $disabled = NULL;

    private $width = null;

    private $height = null;

    private $visibility = null;

    /** @var \exface\Core\Widgets\AbstractWidget the parent widget */
    private $parent = null;

    private $ui = null;

    private $id_specified_by_user = false;

    private $data_connection_alias_specified_by_user = NULL;

    private $prefill_data = null;

    private $uxon_original = null;

    private $hide_caption = false;

    private $page = null;

    private $do_not_prefill = false;

    private $id_space = null;

    private $disable_condition = null;

    private $parentByType = [];

    /**
     *
     * @deprecated use WidgetFactory::create() instead!
     * @param UiPageInterface $page            
     * @param WidgetInterface $parent_widget            
     * @param string $fixed_widget_id            
     */
    function __construct(UiPageInterface $page, WidgetInterface $parent_widget = null, $fixed_widget_id = null)
    {
        $this->page = $page;
        $this->widget_type = static::getWidgetTypeFromClass(get_class($this));
        // Set the parent widget if known
        if ($parent_widget) {
            $this->setParent($parent_widget);
        }
        
        if ($fixed_widget_id) {
            $this->setIdSpecified($fixed_widget_id);
        }
        
        // Add widget to the page. It will now get an autogenerated id
        $page->addWidget($this);
        $this->init();
    }

    public static function getWidgetTypeFromClass($class_name)
    {
        return substr($class_name, (strrpos($class_name, '\\') + 1));
    }

    /**
     * This method is called every time a widget is instantiated an can be used as a hook for additional initializing logics.
     *
     * @return void
     */
    protected function init()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::importUxonObject()
     */
    function importUxonObject(\stdClass $source)
    {
        $uxon = UxonObject::fromAnything($source);
        
        // Save the original UXON description
        $this->uxon_original = $uxon->copy();
        
        // Now do the actual importing
        // First look for an object alias. It must be assigned before the rest because many other properties depend on having the right object
        if ($uxon->hasProperty('object_alias')) {
            $this->setObjectAlias($uxon->getProperty('object_alias'));
        }
        // Same goes for id and id_space
        if ($uxon->hasProperty('id_space')) {
            $this->setIdSpace($uxon->getProperty('id_space'));
            $uxon->unsetProperty('id_space');
        }
        if ($uxon->hasProperty('id')) {
            $this->setId($uxon->getProperty('id'));
            $uxon->unsetProperty('id');
        }
        
        try {
            return $this->importUxonObjectDefault(UxonObject::fromStdClass($source));
        } catch (UxonMapError $e) {
            throw new WidgetPropertyUnknownError($this, 'Unknown UXON property found for widget "' . $this->getWidgetType() . '": ' . $e->getMessage(), '6UNTXJE', $e);
        }
        return;
    }

    public function exportUxonObject()
    {
        $uxon = $this->exportUxonObjectOriginal();
        
        if ($this->getIdSpecified()) {
            $uxon->setProperty('id', $this->getId());
        }
        $uxon->setProperty('widget_type', $this->getWidgetType());
        $uxon->setProperty('object_alias', $this->getMetaObject()->getAliasWithNamespace());
        if (! is_null($this->getCaption())) {
            $uxon->setProperty('caption', $this->getCaption());
        }
        if ($this->isDisabled()) {
            $uxon->setProperty('disabled', $this->isDisabled());
        }
        if ($this->getHint()) {
            $uxon->setProperty('hint', $this->getHint());
        }
        if (! is_null($this->getValue())) {
            $uxon->setProperty('value', $this->getValue());
        }
        if (! is_null($this->getVisibility())) {
            $uxon->setProperty('visibility', $this->getVisibility());
        }
        if (! $this->getWidth()->isUndefined()) {
            $uxon->setProperty('width', $this->getWidth()->toString());
        }
        if (! $this->getHeight()->isUndefined()) {
            $uxon->setProperty('height', $this->getHeight()->toString());
        }
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::exportUxonObjectOriginal()
     */
    public function exportUxonObjectOriginal()
    {
        if ($this->uxon_original instanceof UxonObject) {
            return $this->uxon_original;
        } else {
            return new UxonObject();
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::prefill()
     */
    public final function prefill(DataSheetInterface $data_sheet)
    {
        if ($this->getDoNotPrefill())
            return;
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createWidgetEvent($this, 'Prefill.Before'));
        $this->setPrefillData($data_sheet);
        $this->doPrefill($data_sheet);
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createWidgetEvent($this, 'Prefill.After'));
        return;
    }
    
    /**
     * Prefills the widget using values from the given data sheet.
     * 
     * Override this method for custom prefill logic of a widget. By default it
     * will not do anything at all.
     * 
     * @param DataSheetInterface $data_sheet
     * @return void
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        if (is_null($data_sheet)) {
            $data_sheet = $this->createDataSheet();
        }
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        if (is_null($data_sheet)) {
            $data_sheet = $this->createDataSheet();
        }
        return $data_sheet;
    }
    
    /**
     * Returns TRUE if this widget can be prefilled and FALSE otherwise.
     * @return boolean
     */
    protected function isPrefillable()
    {
        return true;
    }

    protected function createDataSheet()
    {
        return $this->getWorkbench()->data()->createDataSheet($this->getMetaObject());
    }

    /**
     * Sets the widget type.
     * Set to the name of the widget, to instantiate it (e.g. "DataTable").
     *
     * @uxon-property widget_type
     * @uxon-type string
     *
     * @param string $value            
     */
    protected function setWidgetType($value)
    {
        if ($value)
            $this->widget_type = $value;
        return $this;
    }

    /**
     * Sets the caption or title of the widget.
     *
     * @uxon-property caption
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setCaption()
     */
    function setCaption($caption)
    {
        $this->caption = $caption;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getMetaObjectId()
     */
    function getMetaObjectId()
    {
        if (! $this->meta_object_id)
            return $this->getMetaObject()->getId();
        return $this->meta_object_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setMetaObjectId()
     */
    function setMetaObjectId($id)
    {
        $this->meta_object_id = $id;
        return $this;
    }

    /**
     * Explicitly specifies the ID of the widget.
     * The ID must be unique on every page containing the widget and can be used in widget links
     *
     * @uxon-property id
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setId()
     */
    function setId($id)
    {
        return $this->setIdSpecified($id);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::isContainer()
     */
    function isContainer()
    {
        if ($this instanceof iHaveChildren) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getChildren()
     */
    public function getChildren()
    {
        return array();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getChildrenRecursive()
     */
    public function getChildrenRecursive()
    {
        $children = $this->getChildren();
        foreach ($children as $child) {
            $children = array_merge($children, $child->getChildrenRecursive());
        }
        return $children;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getCaption()
     */
    function getCaption()
    {
        return $this->caption;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getHideCaption()
     */
    public function getHideCaption()
    {
        return $this->hide_caption;
    }

    /**
     * Set to TRUE to hide the caption of the widget.
     * FALSE by default.
     *
     * @uxon-property hide caption
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setHideCaption()
     */
    public function setHideCaption($value)
    {
        $this->hide_caption = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getMetaObject()
     */
    function getMetaObject()
    {
        if ($this->meta_object_id) {
            $obj = $this->getUi()->getWorkbench()->model()->getObject($this->meta_object_id);
        } elseif ($this->getObjectQualifiedAlias()) {
            $obj = $this->getUi()->getWorkbench()->model()->getObject($this->getObjectQualifiedAlias());
        } elseif ($this->getParent()) {
            $obj = $this->getParent()->getMetaObject();
        } else {
            throw new WidgetHasNoMetaObjectError($this, 'A widget must have either an object_id, an object_alias or a parent widget with an object reference!');
        }
        $this->setMetaObjectId($obj->getId());
        return $obj;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setMetaObject()
     */
    function setMetaObject(MetaObjectInterface $object)
    {
        return $this->setMetaObjectId($object->getId());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getId()
     */
    function getId()
    {
        if ($id = $this->getIdSpecified()) {
            return $id;
        }
        return $this->getIdAutogenerated();
    }

    public function getIdSpecified()
    {
        return $this->id_specified;
    }

    public function setIdSpecified($value)
    {
        $value = ($this->getIdSpace() ? $this->getIdSpace() . $this->getPage()->getWidgetIdSpaceSeparator() : '') . $value;
        
        // Don't do anything, if the id's are identical
        if ($this->getId() === $value) {
            return $this;
        }
        
        // Just set the id_specified property if there is no id at all at this point
        if (! $this->getId()) {
            $this->id_specified = $value;
            return $this;
        }
        
        $old_id = $this->id_specified;
        
        try {
            $this->id_specified = $value;
            $this->getPage()->addWidget($this);
            if ($old_id) {
                $this->getPage()->removeWidgetById($this->id_specified);
            }
        } catch (WidgetIdConflictError $e) {
            $this->id_specified = $old_id;
            throw $e;
        }
        
        return $this;
    }

    public function getIdAutogenerated()
    {
        return $this->id_autogenerated;
    }

    public function setIdAutogenerated($value)
    {
        $this->id_autogenerated = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getIdSpace()
     */
    public function getIdSpace()
    {
        if (is_null($this->id_space)) {
            if ($this->getParent() && $parent_id_space = $this->getParent()->getIdSpace()) {
                $this->id_space = $parent_id_space;
            } else {
                return '';
            }
        }
        return $this->id_space;
    }

    /**
     * Separates the children of the widget into a separate id space within the page.
     *
     * Multiple widgets with the same id can coexist in a page if the are placed in separate id spaces.
     * This is usefull to reuse complex widgets with live references multiple times on one page. For
     * example, if you have created a complex editor dialog and want to extend from it to create separate
     * buttons for creating a new object and editing one, you can specify a custom id space for each
     * of the buttons - this way, the live references within the button's action will work although
     * the ids specified in them are not unique on the page anymore.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setIdSpace()
     */
    public function setIdSpace($value)
    {
        $id_space_old = $this->id_space;
        $this->id_space = $value;
        // If the id space changes and the widget has an explicit id, make sure the id is reregistered.
        // This will transfer the id into the new id space.
        if ($value != $id_space_old && $this->getIdSpecified()) {
            $this->setId($this->getIdSpecified());
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getWidgetType()
     */
    function getWidgetType()
    {
        return $this->widget_type;
    }

    /**
     * TODO Move to iHaveValue-Widgets or trait
     *
     * @return string|NULL
     */
    public function getValue()
    {
        if ($this->getValueExpression()) {
            return $this->getValueExpression()->toString();
        }
        return null;
    }

    /**
     * TODO Move to iHaveValue-Widgets or trait
     *
     * @return ExpressionInterface
     */
    public function getValueExpression()
    {
        return $this->value;
    }

    /**
     *
     * @return NULL|\exface\Core\Interfaces\Widgets\WidgetLinkInterface
     */
    public function getValueWidgetLink()
    {
        $link = null;
        if ($this->getValueExpression() && $this->getValueExpression()->isReference()) {
            $link = $this->getValueExpression()->getWidgetLink();
            $link->setWidgetIdSpace($this->getIdSpace());
        }
        return $link;
    }

    /**
     * Explicitly sets the value of the widget
     *
     * @uxon-property value
     * @uxon-type Expression|string
     *
     * TODO Move to iHaveValue-Widgets or trait
     *
     * @param ExpressionInterface|string $expression_or_string            
     */
    public function setValue($expression_or_string)
    {
        if ($expression_or_string instanceof expression) {
            $this->value = $expression_or_string;
        } else {
            $this->value = $this->getWorkbench()->model()->parseExpression($expression_or_string, $this->getMetaObject());
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::isDisabled()
     */
    public function isDisabled()
    {
        return $this->disabled;
    }

    /**
     * Set to TRUE to disable the widget.
     * Disabled widgets cannot accept input or interact with the user in any other way.
     *
     * @uxon-property disabled
     * @uxon-type boolean
     *
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setDisabled()
     */
    public function setDisabled($value)
    {
        $this->disabled = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getWidth()
     */
    public function getWidth()
    {
        if (! $this->width) {
            $exface = $this->getWorkbench();
            $this->width = WidgetDimensionFactory::createEmpty($exface);
        }
        return $this->width;
    }

    /**
     * Sets the width of the widget.
     * Set to "1" for default widget width in a template or "max" for maximum width possible.
     *
     * The width can be specified either in
     * - template-specific relative units (e.g. "width: 2" makes the widget twice as wide
     * as the default width of a widget in the current template)
     * - percent (e.g. "width: 50%" will make the widget take up half the available space)
     * - any other template-compatible units (e.g. "width: 200px" will work in CSS-based templates)
     *
     * @uxon-property width
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setWidth()
     */
    public function setWidth($value)
    {
        $exface = $this->getWorkbench();
        $this->width = WidgetDimensionFactory::createFromAnything($exface, $value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getHeight()
     */
    public function getHeight()
    {
        if (! $this->height) {
            $exface = $this->getWorkbench();
            $this->height = WidgetDimensionFactory::createEmpty($exface);
        }
        return $this->height;
    }

    /**
     * Sets the height of the widget.
     * Set to "1" for default widget height in a template or "max" for maximum height possible.
     *
     * The height can be specified either in
     * - template-specific relative units (e.g. "height: 2" makes the widget twice as high
     * as the default width of a widget in the current template)
     * - percent (e.g. "height: 50%" will make the widget take up half the available space)
     * - any other template-compatible units (e.g. "height: 200px" will work in CSS-based templates)
     *
     * @uxon-property height
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setHeight()
     */
    public function setHeight($value)
    {
        $exface = $this->getWorkbench();
        $this->height = WidgetDimensionFactory::createFromAnything($exface, $value);
        return $this;
    }

    /**
     * Returns the full alias of the main meta object (prefixed by the app namespace - e.g.
     * CRM.CUSTOMER)
     */
    public function getObjectQualifiedAlias()
    {
        return $this->object_qualified_alias;
    }

    /**
     * Sets the alias of the main object of the widget.
     * Use qualified aliases (with namespace)!
     *
     * @uxon-property object_alias
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setObjectAlias()
     */
    public function setObjectAlias($full_or_object_alias)
    {
        // If it's a fully qualified alias, use it directly
        if ($ns = $this->getUi()->getWorkbench()->model()->getNamespaceFromQualifiedAlias($full_or_object_alias)) {
            $this->object_qualified_alias = $full_or_object_alias;
            $this->object_alias = $this->getUi()->getWorkbench()->model()->getObjectAliasFromQualifiedAlias($full_or_object_alias);
        }  // ... if the namespace is missing, get it from the app of the parent object
else {
            if ($this->getParent()) {
                $ns = $this->getParent()->getMetaObject()->getNamespace();
            }
            
            if (! $ns) {
                throw new WidgetConfigurationError($this, 'Cannot set object_alias property for widget "' . $this->getId() . '": neither a namespace is specified, nor is there a parent widget to take it from!', '6UOD4TW');
            }
            $this->object_alias = $full_or_object_alias;
            $this->object_qualified_alias = $ns . NameResolver::NAMESPACE_SEPARATOR . $this->object_alias;
        }
        // IMPORTANT: unset the meta_object_id of this class, because it may already have been initialized previously and would act as a cache
        // for the meta object.
        unset($this->meta_object_id);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getObjectRelationPathFromParent()
     */
    public function getObjectRelationPathFromParent()
    {
        if (is_null($this->object_relation_path_from_parent)) {
            // If there is no relation to the parent set yet, see if there is a parent.
            // If not, do not do anything - maybe there will be some parent when the method is called the next time
            if ($this->getParent()) {
                // If there is no relation path yet, create one
                $this->object_relation_path_from_parent = RelationPathFactory::createForObject($this->getParent()->getMetaObject());
                // If the parent is based on another object, search for a relation to it - append it to the path if found
                if (! $this->getParent()->getMetaObject()->is($this->getMetaObject())) {
                    if ($this->object_relation_path_to_parent) {
                        // If we already know the path from this widgets object to the parent, just reverse it
                        $this->object_relation_path_from_parent = $this->getObjectRelationPathToParent()->reverse();
                    } elseif ($rel = $this->getParent()->getMetaObject()->findRelation($this->getMetaObject(), true)) {
                        // Otherwise, try to find a path automatically
                        $this->object_relation_path_from_parent->appendRelation($rel);
                    }
                }
            }
        } elseif (! ($this->object_relation_path_from_parent instanceof RelationPath)) {
            $this->object_relation_path_from_parent = RelationPathFactory::createFromString($this->getParent()->getMetaObject(), $this->object_relation_path_from_parent);
        } else {
            // If there is a relation path already built, check if it still fits to the current parent widget (which might have changed)
            // If not, removed the cached path and runt the getter again to try to find a new path
            if (! $this->getParent()->getMetaObject()->is($this->object_relation_path_from_parent->getStartObject())) {
                $this->object_relation_path_from_parent = null;
                return $this->getObjectRelationPathFromParent();
            }
        }
        return $this->object_relation_path_from_parent;
    }

    /**
     * Sets the relation path from the parent widget's object to this widget's object
     *
     * @uxon-property object_relation_path_from_parent
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setObjectRelationPathFromParent()
     */
    public function setObjectRelationPathFromParent($string)
    {
        $this->object_relation_path_from_parent = $string;
        if ($this->isObjectInheritedFromParent()) {
            $this->setObjectAlias($this->getParent()->getMetaObject()->getRelatedObject($string)->getAliasWithNamespace());
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::isObjectInheritedFromParent()
     */
    public function isObjectInheritedFromParent()
    {
        if (is_null($this->object_qualified_alias) && is_null($this->meta_object_id)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getObjectRelationPathToParent()
     */
    public function getObjectRelationPathToParent()
    {
        if (is_null($this->object_relation_path_to_parent)) {
            // If there is no relation to the parent set yet, see if there is a parent.
            // If not, do not do anything - maybe there will be some parent when the method is called the next time
            if ($this->getParent()) {
                // If there is no relation path yet, create one
                $this->object_relation_path_to_parent = RelationPathFactory::createForObject($this->getMetaObject());
                // If the parent is based on another object, search for a relation to it - append it to the path if found
                if (! $this->getParent()->getMetaObject()->is($this->getMetaObject())) {
                    if ($this->object_relation_path_from_parent) {
                        // If we already know the path from the parents object to this widget, just reverse it
                        $this->object_relation_path_to_parent = $this->getObjectRelationPathToParent()->reverse();
                    } elseif ($rel = $this->getMetaObject()->findRelation($this->getParent()->getMetaObject(), true)) {
                        $this->object_relation_path_to_parent->appendRelation($rel);
                    }
                }
            }
        } elseif (! ($this->object_relation_path_to_parent instanceof RelationPath)) {
            // If there is a path, but it is a string (e.g. it was just set via UXON import), create an object from it
            $this->object_relation_path_to_parent = RelationPathFactory::createFromString($this->getMetaObject(), $this->object_relation_path_to_parent);
        } else {
            // If there is a relation path already built, check if it still fits to the current parent widget (which might have changed)
            // If not, removed the cached path and runt the getter again to try to find a new path
            if (! $this->getParent()->getMetaObject()->is($this->object_relation_path_to_parent->getEndObject())) {
                $this->object_relation_path_to_parent = null;
                return $this->getObjectRelationPathToParent();
            }
        }
        return $this->object_relation_path_to_parent;
    }

    /**
     * Sets the relation path from this widget's meta object to the object of the parent widget
     *
     * @uxon-property object_relation_path_to_parent
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setObjectRelationPathToParent()
     */
    public function setObjectRelationPathToParent($string)
    {
        $this->object_relation_path_to_parent = $string;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getPageId()
     */
    public function getPageId()
    {
        return $this->getPage()->getId();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getParent()
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setParent()
     */
    public function setParent(WidgetInterface $widget)
    {
        $this->parent = $widget;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getUi()
     */
    public function getUi()
    {
        return $this->getPage()->getWorkbench()->ui();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getHint()
     */
    public function getHint()
    {
        if (! $this->hint && ($this instanceof iShowSingleAttribute) && $this->getAttribute()) {
            $this->setHint($this->getAttribute()->getHint());
        }
        return $this->hint;
    }

    /**
     * Sets a hint message for the widget.
     * The hint will typically be used for pop-overs, etc.
     *
     * @uxon-property hint
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setHint()
     */
    public function setHint($value)
    {
        $this->hint = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::isHidden()
     */
    public function isHidden()
    {
        return $this->getVisibility() == EXF_WIDGET_VISIBILITY_HIDDEN ? true : false;
    }

    /**
     * Set to TRUE to hide the widget.
     * The same effect can be achieved by setting "visibility: hidden".
     *
     * Setting "hidden: false" will revert visibility to normal - just like "visibility: normal".
     *
     * @uxon-property hidden
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setHidden()
     */
    public function setHidden($value)
    {
        if ($value) {
            $this->setVisibility(EXF_WIDGET_VISIBILITY_HIDDEN);
        } else {
            $this->setVisibility(EXF_WIDGET_VISIBILITY_NORMAL);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getVisibility()
     */
    public function getVisibility()
    {
        if ($this->visibility === null)
            $this->setVisibility(EXF_WIDGET_VISIBILITY_NORMAL);
        return $this->visibility;
    }

    /**
     * Sets the visibility of the widget: normal, hidden, optional, promoted.
     *
     * @uxon-property visibility
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setVisibility()
     */
    public function setVisibility($value)
    {
        if (is_int($value)){
            $this->visibility = $value;
        } else {
            if (! defined('EXF_WIDGET_VISIBILITY_' . mb_strtoupper($value))) {
                throw new WidgetPropertyInvalidValueError($this, 'Invalid visibility value "' . $value . '" for widget "' . $this->getWidgetType() . '"!', '6T90UH3');
            }
            $this->visibility = constant('EXF_WIDGET_VISIBILITY_'. mb_strtoupper($value));
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getPrefillData()
     */
    public function getPrefillData()
    {
        return $this->prefill_data;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setPrefillData()
     */
    public function setPrefillData(DataSheetInterface $data_sheet)
    {
        $this->prefill_data = $data_sheet;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::implementsInterface()
     */
    public function implementsInterface($interface_name)
    {
        $type_class = '\\exface\\Core\\Interfaces\\Widgets\\' . $interface_name;
        if ($this instanceof $type_class) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::is()
     */
    public function is($type)
    {
        $type_class = '\\exface\\Core\\Widgets\\' . $type;
        if ($this instanceof $type_class) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::isExactly()
     */
    public function isExactly($widget_type)
    {
        $type_class = 'exface\\Core\\Widgets\\' . $widget_type;
        if (get_class($this) === $type_class) {
            return true;
        }
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getActions()
     */
    public function getActions($qualified_action_alias = null, $action_id = null)
    {
        $actions = array();
        foreach ($this->getChildren() as $child) {
            // If the child triggers an action itself, check if the action fits the filters an add it to the array
            if ($child instanceof iTriggerAction) {
                if (($qualified_action_alias && $child->getAction()->getAliasWithNamespace() == $qualified_action_alias) || ($action_id && $child->getAction()->getId() == $action_id) || (! $qualified_action_alias && ! $action_id)) {
                    $actions[] = $child->getAction();
                }
            }
            
            // If the child has children itself, call the method recursively
            $actions = array_merge($actions, $child->getActions($qualified_action_alias, $action_id));
        }
        return $actions;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getAggregations()
     */
    public function getAggregations()
    {
        return array();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setDataConnectionAlias()
     */
    public function setDataConnectionAlias($value)
    {
        $this->data_connection_alias_specified_by_user = $value;
        $this->getMetaObject()->setDataConnectionAlias($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::createWidgetLink()
     */
    public function createWidgetLink()
    {
        $exface = $this->getWorkbench();
        $link = new WidgetLink($exface);
        $link->setWidgetId($this->getId());
        $link->setPageId($this->getPageId());
        return $link;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getPage()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::getPage()
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Returns the translation string for the given message id.
     *
     * This is a shortcut for calling $this->getApp()->getTranslator()->translate().
     *
     * @see Translation::translate()
     *
     * @param string $message_id            
     * @param array $placeholders            
     * @param float $number_for_plurification            
     * @return string
     */
    public function translate($message_id, array $placeholders = null, $number_for_plurification = null)
    {
        $message_id = trim($message_id);
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($message_id, $placeholders, $number_for_plurification);
    }

    public function getDoNotPrefill()
    {
        return $this->do_not_prefill;
    }

    public function setDoNotPrefill($value)
    {
        $this->do_not_prefill = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\iCanBeConvertedToUxon|\exface\Core\CommonLogic\Model\Condition
     */
    public function getDisableCondition()
    {
        return $this->disable_condition;
    }

    /**
     * Sets a condition to disable the widget.
     *
     * E.g.:
     * "disable_condition": {
     * "widget_link": "consumer!CONSUMER_MAIL_PHONE",
     * "comparator": "!=",
     * "value": ""
     * }
     * means the current widget is disabled when the column CONSUMER_MAIL_PHONE of
     * widget consumer is not empty. Can be usefully combined with a value-reference
     * to the same widget and column.
     *
     * @uxon-property disable_condition
     * @uxon-type object
     *
     * @param UxonObject $value            
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function setDisableCondition($value)
    {
        $this->disable_condition = $value;
        return $this;
    }

    /**
     * Returns the closest parent widget which implements the passed class or interface.
     * 
     * Returns null if no such parent widget exists.
     *
     * @param string $typeName            
     * @return AbstractWidget
     */
    public function getParentByType(string $typeName)
    {
        if (! array_key_exists($typeName, $this->parentByType)) {
            $widget = $this;
            while ($widget->getParent()) {
                $widget = $widget->getParent();
                
                // Ein Filter is eher ein Wrapper als ein Container (kann nur ein Widget enthalten).
                if (($typeName == 'exface\\Core\\Interfaces\\Widgets\\iContainOtherWidgets') && ($widget instanceof $typeName) && ($widget instanceof Filter)) {
                    continue;
                }
                
                if ($widget instanceof $typeName) {
                    $this->parentByType[$typeName] = $widget;
                    break;
                }
            }
            
            if (! array_key_exists($typeName, $this->parentByType)) {
                $this->parentByType[$typeName] = null;
            }
        }
        return $this->parentByType[$typeName];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy()
    {
        return WidgetFactory::createFromUxon($this->getPage(), $this->exportUxonObject());
    }
}
?>