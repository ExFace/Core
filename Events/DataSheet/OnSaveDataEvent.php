<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired right after a data operation (create, update or delete) is finished.
 *
 * **WARNING:** this event is known to have side effects. Read these docs carefully. Prefer dedicated `OnCreate`,
 * `OnUpdate` and `OnDelete` if you are unsure!
 * 
 * This event is fired right after the dedicated events. So any listeners will always be called after the listeners of
 * the dedicated events - regardless of their priorities!
 * 
 * See details in `OnBeforeSaveDataEvent`!
 *  
 * @event exface.Core.DataSheet.OnSaveData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnSaveDataEvent extends OnBeforeSaveDataEvent
{}