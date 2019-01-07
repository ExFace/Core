<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;

/**
 * A UXON editor with autosuggest, templates and validation.
 *
 * @author Andrej Kabachnik
 *        
 */
class InputUxon extends InputJson
{
    private $autosuggest = true;
    
    private $rootEntity = null;
    
    private $rootObjectAlias = null;
    
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
    public function getRootEntity() : ?ExpressionInterface
    {
        return $this->rootEntity;
    }
    
    /**
     * Specify the the root UXON entity class for this input widget: e.g. a specific widget or action class.
     * 
     * The entity class can either be specified directly or via widget link.
     * 
     * If not set explicitly, the default entity for the selected UXON schema will be used (e.g.
     * `\exface\Core\Widgets\AbstractWidget` for the widget schema).
     * 
     * @uxon-property root_entity
     * @uxon-type string
     * 
     * @param string $value
     * @return InputUxon
     */
    public function setRootEntity(string $value) : InputUxon
    {
        $this->rootEntity = ExpressionFactory::createForObject($this->getMetaObject(), $value);
        return $this;
    }
    
    /**
     *
     * @return ExpressionInterface|NULL
     */
    public function getRootObjectAlias() : ?ExpressionInterface
    {
        return $this->rootObjectAlias;
    }
    
    /**
     * Specify the meta object of the root level of the UXON: either directly or via widget link.
     * 
     * If no meta object alias is specified, the root UXON entity must have an `object_alias`
     * property.
     * 
     * @uxon-property root_object_alias
     * @uxon-type metamodel:object
     * 
     * @param string $value
     * @return InputUxon
     */
    public function setRootObjectAlias(string $value) : InputUxon
    {
        $this->rootObjectAlias = ExpressionFactory::createForObject($this->getMetaObject(), $value);
        return $this;
    }
}