<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataMappingInterface;

interface DataMappingExceptionInterface extends DataMapperExceptionInterface
{
    /**
     *
     * @return DataMappingInterface
     */
    public function getMapping();
}