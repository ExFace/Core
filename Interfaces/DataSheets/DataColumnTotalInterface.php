<?php

namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;

interface DataColumnTotalInterface extends iCanBeConvertedToUxon
{

    function __construct(DataColumnInterface $column, $function_name = null);

    /**
     *
     * @return DataColumnInterface
     */
    public function getColumn();

    /**
     *
     * @param DataColumnInterface $value            
     */
    public function setColumn(DataColumnInterface $value);

    /**
     *
     * @return string
     */
    public function getFunction();

    /**
     *
     * @param string $value            
     * @throws InvalidArgumentException
     * @return \exface\Core\Interfaces\DataSheets\DataColumnTotalInterface
     */
    public function setFunction($value);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon);
}