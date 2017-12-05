<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;

Interface DataTypeExceptionInterface
{

    /**
     *
     * @param DataTypeInterface $dataType            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(DataTypeInterface $dataType, $message, $code = null, $previous = null);

    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType();

    /**
     *
     * @param DataTypeInterface $sheet            
     * @return DataTypeExceptionInterface
     */
    public function setDataType(DataTypeInterface $dataType);
}
?>