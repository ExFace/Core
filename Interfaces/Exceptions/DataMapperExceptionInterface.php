<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;

interface DataMapperExceptionInterface extends ExceptionInterface
{
    /**
     *
     * @return DataSheetMapperInterface
     */
    public function getMapper() : DataSheetMapperInterface;
}