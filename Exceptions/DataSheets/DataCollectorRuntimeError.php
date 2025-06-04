<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Widgets\DebugMessage;

/**
 * Exception thrown if a data collector fails to retrieve its data.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataCollectorRuntimeError extends DataSheetRuntimeError
{

}