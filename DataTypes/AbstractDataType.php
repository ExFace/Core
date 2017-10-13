<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;

abstract class AbstractDataType implements DataTypeInterface
{
    use ImportUxonObjectTrait;

    private $name_resolver = null;

    private $name = null;
    
    private $shortDescription = null;
    
    private $defaultWidgetUxon = null;
    
    private $parseErrorCode = null;

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
        } catch (DataTypeValidationError $e) {
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
        return $this->getNameResolver()->getAlias();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNameResolver()->getAliasWithNamespace();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace(){
        return $this->getNameResolver()->getNamespace();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getApp()
     */
    public function getApp()
    {
        return $this->getWorkbench()->getApp($this->getNameResolver()->getAppAlias());
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
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getDefaultWidgetUxon()
     */
    public function getDefaultWidgetUxon()
    {
        if (is_null($this->defaultWidgetUxon)) {
            $this->defaultWidgetUxon = new UxonObject();
        }
        
        // Make sure, the UXON has allways an explicit widget type! Otherwise checks for
        // widget type later in the code might put in their defaults potentially uncompatible
        // with properties set here or anywhere inbetween.
        if (! $this->defaultWidgetUxon->hasProperty('widget_type')) {
            $this->defaultWidgetUxon->setProperty('widget_type', $this->getWorkbench()->getConfig()->getOption('TEMPLATES.WIDGET_FOR_UNKNOWN_DATA_TYPES'));
        }
        
        return $this->defaultWidgetUxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setDefaultWidgetUxon()
     */
    public function setDefaultWidgetUxon(UxonObject $defaultWidgetUxon)
    {
        $this->defaultWidgetUxon = $defaultWidgetUxon;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getParseErrorCode()
     */
    public function getParseErrorCode()
    {
        return $this->parseErrorCode;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::setParseErrorCode()
     */
    public function setParseErrorCode($parseErrorCode)
    {
        $this->parseErrorCode = $parseErrorCode;
        return $this;
    }

}
?>