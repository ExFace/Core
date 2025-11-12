<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataMatcherInterface;
use exface\Core\Interfaces\DataSheets\DataMatchInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;

interface DataMatcherExceptionInterface extends ExceptionInterface
{
    /**
     *
     * @return DataMatcherInterface
     */
    public function getMatcher() : DataMatcherInterface;

    /**
     * @return DataMatchInterface|null
     */
    public function getMatch() : ?DataMatchInterface;

    /**
     * @return DataLogBookInterface|null
     */
    public function getLogbook() : ?DataLogBookInterface;
}