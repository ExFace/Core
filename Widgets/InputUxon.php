<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * A UXON editor with autosuggest, facades and validation.
 *
 * @author Andrej Kabachnik
 *        
 */
class InputUxon extends InputJson
{
    private $autosuggest = true;
    
    private $prototype = null;
    
    private $rootObjectSelector = null;
    
    /**
     * Specifies the UXON schema: widget, action, datatype, behavior, etc.
     * 
     * @uxon-property schema
     * @uxon-type [widget,action,behavior,datatype]
     * 
     * @see \exface\Core\Widgets\InputJson::setSchema()
     */
    public function setSchema(string $value) : InputJson
    {
        return parent::setSchema($value);
    }
    
    /**
     * If no schema was set explicitly, the UXON input will use the default schema "uxon".
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputJson::getSchema()
     */
    public function getSchema() : ?string
    {
        return parent::getSchema() ?? 'uxon';
    }
    
    /**
     *
     * @return bool
     */
    public function getAutosuggest() : bool
    {
        return $this->autosuggest;
    }
    
    /**
     * Set to FALSE to disable UXON autosuggest for this editor.
     * 
     * @uxon-property autosuggest
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool|string $value
     * @return InputUxon
     */
    public function setAutosuggest($value) : InputUxon
    {
        $this->autosuggest = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * @return ExpressionInterface|NULL
     */
    public function getRootPrototype() : ?ExpressionInterface
    {
        return $this->prototype;
    }
    
    /**
     * Specify the the root UXON prototype selector for this input widget: e.g. a specific widget or action.
     * 
     * The prototype selector can either be a qualified class name or a path to a PHP file relative to the
     * vendor folder in this installation. The prototype can either be specified directly or via widget link.
     * 
     * If not set explicitly, the default entity for the selected UXON schema will be used (e.g.
     * `\exface\Core\Widgets\AbstractWidget` for the widget schema).
     * 
     * @uxon-property root_prototype
     * @uxon-type string
     * 
     * @param string $value
     * @return InputUxon
     */
    public function setRootPrototype(string $value) : InputUxon
    {
        $expr = ExpressionFactory::createForObject($this->getMetaObject(), $value);
        if (! $expr->isConstant() && ! $expr->isReference()) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $value . '" for property root_prototype of widget ' . $this->getWidgetType() . ': expecting an object selector string or a widget link!');
        }
        $this->prototype = $expr;
        return $this;
    }
    
    /**
     *
     * @return ExpressionInterface|NULL
     */
    public function getRootObject() : ?ExpressionInterface
    {
        return $this->rootObjectSelector;
    }
    
    /**
     * The meta object of the root level of the UXON: either an object selector (uid or alias) or via widget link to a selector.
     * 
     * If no meta object alias is specified, the root UXON entity must have an `object_alias`
     * property.
     * 
     * @uxon-property root_object
     * @uxon-type metamodel:object|metamodel:widget_link
     * 
     * @param string $value
     * @return InputUxon
     */
    public function setRootObject(string $value) : InputUxon
    {
        $expr = ExpressionFactory::createForObject($this->getMetaObject(), $value);
        if (! $expr->isConstant() && ! $expr->isReference()) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $value . '" for property root_object of widget ' . $this->getWidgetType() . ': expecting an object selector string or a widget link!');
        }
        $this->rootObjectSelector = $expr;
        return $this;
    }
}