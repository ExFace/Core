<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;

/**
 * This trait enables an exception to output data type specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataTypeExceptionTrait {
    
    use ExceptionTrait {
		createWidget as createWidgetViaTrait;
	}

    private $dataType = null;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
     */
    public function __construct(DataTypeInterface $dataType, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataType($dataType);
    }
    
    /**
     * @return DataTypeInterface
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * @param DataTypeInterface $dataType
     * @return DataTypeExceptionInterface
     */
    public function setDataType(DataTypeInterface $dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }
  
}
?>