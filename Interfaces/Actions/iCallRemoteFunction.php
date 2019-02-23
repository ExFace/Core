<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Actions, that perform remote function calls (RFC), should implement this interface.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCallRemoteFunction extends iAccessDataSources
{
    /**
     * 
     * @return string
     */
    public function getRemoteFunctionName() : string;
    
    /**
     * 
     * @return array
     */
    public function getParameters() : array;
}