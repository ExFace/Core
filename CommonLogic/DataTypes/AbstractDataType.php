<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\ValueObjectInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\Factories\DataTypeFactory;

abstract class AbstractDataType implements DataTypeInterface
{
    use ImportUxonObjectTrait;
    use AliasTrait {
        getAlias as getAliasFromSelector;
    }
    
    private $workbench = null;

    private $selector = null;
    
    private $alias = null;
    
    private $app = null;

    private $name = null;
    
    private $shortDescription = null;
    
    private $defaultEditorUxon = null;
    
    private $validationErrorCode = null;
    
    private $validationErrorText = null;
    
    private $value = null;

    public function __construct(DataTypeSelectorInterface $selector, $value = null, UxonObject $configuration = null)
    {
        $this->workbench = $selector->getWorkbench();
        $this->selector = $selector;
        if (! is_null($configuration)) {
            $this->importUxonObject($configuration);
        }
        if (! is_null($value)) {
            $this->setValue($value);
        }
    }
    
    protected function getClassnameSuffixToStripFromAlias()
    {
        return 'DataType';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getModel()
     */
    public function getModel()
    {
        return $this->getWorkbench()->model();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getName()
     */
    public function getName()
    {
        if (is_null($this->name)) {
            $name = substr(get_class($this), (strrpos(get_class($this), "\\") + 1));
            $name = str_replace('DataType', '', $name);
            $this->name = $name;
        }
        return $this->name;
    }
    
    public function setName($string)
    {
        $this->name = $string;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::is()
     */
    public function is($data_type_or_resolvable_name)
    {
        if ($data_type_or_resolvable_name instanceof AbstractDataType) {
            $class = get_class($data_type_or_resolvable_name);
        } else {
            $data_type = DataTypeFactory::createFromString($this->getWorkbench(), $data_type_or_resolvable_name);
            $class = get_class($data_type);
        }
        return ($this instanceof $class);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::cast()
     */
    public static function cast($string, $emptyValue = null)
    {
        return static::isEmptyValue($string) === true ? null : $string;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::isEmptyValue()
     */
    public static function isEmptyValue($string) : bool
    {
        return $string === null || $string === '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::parse()
     */
    public function parse($string)
    {
        return static::cast($string);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::isValidValue()
     */
    public function isValidValue($string)
    {
        try {
            static::cast($string);
        } catch (DataTypeCastingError $e) {
            return false;
        }
        
        try {
            $this->parse($string);
        } catch (DataTypeValidationError $e) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::DESC($this->getWorkbench());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getSelector()
     */
    public function getSelector() : DataTypeSelectorInterface
    {
        return $this->selector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias === null ? $this->getAliasFromSelector() : $this->alias;
    }
    
    public function setAlias($string)
    {
        $this->alias = $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace(){
        return $this->getApp()->getAliasWithNamespace();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getApp()
     */
    public function getApp()
    {
        return is_null($this->app) ? $this->getWorkbench()->getApp($this->selector->getAppSelector()) : $this->app;
    }
    
    public function setApp(AppInterface $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaModelPrototypeInterface::getPrototypeClassName()
     */
    public static function getPrototypeClassName()
    {
        return "\\" . __CLASS__;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy()
    {
        return clone $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('name', $this->getName());
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getShortDescription()
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::setShortDescription()
     */
    public function setShortDescription($shortDescription)
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getDefaultEditorUxon()
     */
    public function getDefaultEditorUxon()
    {
        if (is_null($this->defaultEditorUxon)) {
            $this->defaultEditorUxon = new UxonObject();
        }
        
        // Make sure, the UXON has allways an explicit widget type! Otherwise checks for
        // widget type later in the code might put in their defaults potentially uncompatible
        // with properties set here or anywhere inbetween.
        if (! $this->defaultEditorUxon->hasProperty('widget_type')) {
            $this->defaultEditorUxon->setProperty('widget_type', $this->getWorkbench()->getConfig()->getOption('TEMPLATES.DEFAULT_WIDGET_FOR_UNKNOWN_DATA_TYPES'));
        }
        
        return $this->defaultEditorUxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::setDefaultEditorUxon()
     */
    public function setDefaultEditorUxon(UxonObject $defaultEditorUxon)
    {
        $this->defaultEditorUxon = $defaultEditorUxon;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::setDefaultEditorWidget()
     */
    public function setDefaultEditorWidget(UxonObject $uxon)
    {
        return $this->setDefaultEditorUxon($uxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getValidationErrorCode()
     */
    public function getValidationErrorCode()
    {
        return $this->validationErrorCode;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::setValidationErrorCode()
     */
    public function setValidationErrorCode($validationErrorCode)
    {
        $this->validationErrorCode = $validationErrorCode;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::getValidationErrorText()
     */
    public function getValidationErrorText()
    {
        return $this->validationErrorText;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::setValidationErrorText()
     */
    public function setValidationErrorText($string)
    {
        $this->validationErrorText = $string;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ValueObjectInterface::withValue()
     */
    public final function withValue($value) : ValueObjectInterface
    {
        return $this->copy()->setValue($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ValueObjectInterface::getValue()
     */
    public final function getValue()
    {
        return $this->value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ValueObjectInterface::hasValue()
     */
    public function hasValue() : bool
    {
        return $this->value !== null;
    }

    protected final function setValue($value)
    {
        $this->value = $this->parse($value);
        return $this;
    }
    
    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ValueObjectInterface::equals()
     */
    public final function equals(ValueObjectInterface $valueObject) : bool
    {
        // TODO compare uxon configuration
        return $this->getValue() === $valueObject->getValue() && $this->getAliasWithNamespace() === $valueObject->getAliasWithNamespace() && get_called_class() == get_class($valueObject);
    }

}
?>