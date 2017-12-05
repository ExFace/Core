<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;

Interface DataSheetMapperExceptionInterface extends ExceptionInterface
{

    /**
     *
     * @param DataSheetMapperInterface $data_sheet            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(DataSheetMapperInterface $data_sheet, $message, $code = null, $previous = null);

    /**
     *
     * @return DataSheetMapperInterface
     */
    public function getMapper();

    /**
     *
     * @param DataSheetInterface $sheet            
     * @return DataSheetMapperExceptionInterface
     */
    public function setMapper(DataSheetMapperInterface $sheet);
}
?>