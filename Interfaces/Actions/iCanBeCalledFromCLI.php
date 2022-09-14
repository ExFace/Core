<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Actions, that can be used as CLI commands.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCanBeCalledFromCLI extends ActionInterface
{
    const PARAMETER_GROUP_ARGUMENTS = 'arguments';
    
    const PARAMETER_GROUP_OPTIONS = 'options';
    
    /**
     * 
     * @return ServiceParameterInterface[]
     */
    public function getCliArguments() : array;
    
    /**
     * 
     * @return ServiceParameterInterface[]
     */
    public function getCliOptions() : array;
}