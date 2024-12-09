<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired after a data sheet finished deleting it's data in the corresponding data sources (but before the transaction is committed!).
 * 
 * @event exface.Core.DataSheet.OnDeleteData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnDeleteDataEvent extends AbstractDataSheetEvent
{
    
}