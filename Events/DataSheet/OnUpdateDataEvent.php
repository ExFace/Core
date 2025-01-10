<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired once a data sheet finished updating it's data in the corresponding data sources (but before the transaction is committed!).
 * 
 * @event exface.Core.DataSheet.OnUpdateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnUpdateDataEvent extends AbstractDataSheetEvent
{
    
}