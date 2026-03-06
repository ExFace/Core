<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\DataTypes\DataSheetDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\DataChangeEventInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

/**
 * Event fired right before a data operation (create, update or delete) is finished.
 *  
 * @event exface.Core.DataSheet.OnSaveData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnSaveDataEvent extends OnBeforeSaveDataEvent
{}