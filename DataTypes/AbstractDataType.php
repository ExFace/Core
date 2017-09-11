<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;

abstract class AbstractDataType implements DataTypeInterface
{

    private $name_resolver = null;

    private $name = null;

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
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::parse()
     */
    public static function parse($string)
    {
        return $string;
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
            static::parse($string);
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
}
?>