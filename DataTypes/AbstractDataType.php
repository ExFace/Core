<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Constants\SortingDirections;

abstract class AbstractDataType implements DataTypeInterface
{

    private $exface = null;

    private $name = null;

    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\DataTypeInterface::getModel()
     */
    public function getModel()
    {
        return $this->getWorkbench()->model;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
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
    public function is($data_type_or_string)
    {
        if ($data_type_or_string instanceof AbstractDataType) {
            $class = get_class($data_type_or_string);
        } else {
            $class = __NAMESPACE__ . '\\' . $data_type_or_string . 'DataType';
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
}
?>