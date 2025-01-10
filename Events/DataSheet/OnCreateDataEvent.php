<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired after a data sheet finished creating it's data in the corresponding data sources (but before the transaction is committed!).
 * 
 * @event exface.Core.DataSheet.OnCreateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnCreateDataEvent extends AbstractDataSheetEvent
{
    
}