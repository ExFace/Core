<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired after a data sheet finished preparing and enriching its data, but before writing it to the data source.
 * 
 * @event exface.Core.DataSheet.OnBeforeUpdateDataWrite
 * 
 * @author Georg Bieger
 *
 */
class OnBeforeUpdateDataWriteEvent extends OnBeforeUpdateDataEvent
{

}