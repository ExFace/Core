<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired once a data sheet finished replacing it's data in the corresponding data sources (but before the transaction is committed!).
 * 
 * @event exface.Core.DataSheet.OnReplaceData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnReplaceDataEvent extends AbstractDataSheetEvent
{
    
}