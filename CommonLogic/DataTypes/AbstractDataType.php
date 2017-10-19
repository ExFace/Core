<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\AppInterface;

abstract class AbstractDataType implements DataTypeInterface
{
    use ImportUxonObjectTrait;

    private $name_resolver = null;
    
    private $alias = null;
    
    private $app = null;

    private $name = null;
    
    private $shortDescription = null;
    
    private $defaultEditorUxon = null;
    
    private $validationErrorCode = null;
    
    private $validationErrorText = null;
    
    private $value = null;

    public function __construct(NameResolverInterface $name_resolver)
    {
        $this->name_resolver = $name_resolver;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getModel()
     */
    public function getModel()
    {
        return $this->getWorkbench()->model();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->name_resolver->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getName()
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
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::is()
     */
    public function is($data_type_or_resolvable_name)
    {
        if ($data_type_or_resolvable_name instanceof AbstractDataType) {
            $class = get_class($data_type_or_resolvable_name);
        } else {
            $name_resolver = NameResolver::createFromString($data_type_or_resolvable_name, NameResolver::OBJECT_TYPE_DATATYPE, $this->getWorkbench());
            if ($name_resolver->classExists()){
                $class = $name_resolver->getClassNameWithNamespace();
            } else {
                return false;
            }
        }
        return ($this instanceof $class);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::cast()
     */
    public static function cast($string)
    {
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::parse()
     */
    public function parse($string)
    {
        return static::cast($string);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::validate()
     */
    public static function validate($string)
    {
        try {
            static::cast($string);
        } catch (DataTypeCastingError $e) {
            return false;
        }
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirections::DESC();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getNameResolver()
     */
    public function getNameResolver()
    {
        return $this->name_resolver;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return is_null($this->alias) ? $this->getNameResolver()->getAlias() : $this->alias;
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
        return $this->getNamespace() . NameResolver::NAMESPACE_SEPARATOR . $this->getAlias();
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
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getApp()
     */
    public function getApp()
    {
        return is_null($this->app) ? $this->getWorkbench()->getApp($this->getNameResolver()->getAppAlias()) : $this->app;
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
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getShortDescription()
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setShortDescription()
     */
    public function setShortDescription($shortDescription)
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getDefaultEditorUxon()
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
            $this->defaultEditorUxon->setProperty('widget_type', $this->getWorkbench()->getConfig()->getOption('TEMPLATES.WIDGET_FOR_UNKNOWN_DATA_TYPES'));
        }
        
        return $this->defaultEditorUxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setDefaultEditorUxon()
     */
    public function setDefaultEditorUxon(UxonObject $defaultEditorUxon)
    {
        $this->defaultEditorUxon = $defaultEditorUxon;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setDefaultEditorWidget()
     */
    public function setDefaultEditorWidget(UxonObject $uxon)
    {
        return $this->setDefaultEditorUxon($uxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getValidationErrorCode()
     */
    public function getValidationErrorCode()
    {
        return $this->validationErrorCode;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setValidationErrorCode()
     */
    public function setValidationErrorCode($validationErrorCode)
    {
        $this->validationErrorCode = $validationErrorCode;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getValidationErrorText()
     */
    public function getValidationErrorText()
    {
        return $this->validationErrorText;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setValidationErrorText()
     */
    public function setValidationErrorText($string)
    {
        $this->validationErrorText = $string;
        return $this;
    }
    
    public final function withValue($value)
    {
        return $this->copy()->setValue($value);
    }
    
    public final function getValue()
    {
        return $this->value;
    }
    
    public function hasValue()
    {
        return ! is_null($this->value);
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
     * Compares one value object with another.
     *
     * @return boolean
     */
    public final function equals(DataTypeInterface $valueObject)
    {
        // TODO compare uxon configuration
        return $this->getValue() === $valueObject->getValue() && $this->getAliasWithNamespace() === $valueObject->getAliasWithNamespace() && get_called_class() == get_class($valueObject);
    }

}
?>