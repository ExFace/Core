<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Actions, that modify data in data source must implement this interface.
 * Only these actions are saved in the action history!
 * 
 * @author Andrej Kabachnik
 *        
 */
interface iModifyData extends iAccessDataSources
{
}