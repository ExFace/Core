<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Interface for actions, that call external services: PHP functions, RFC, RPC, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCallService extends iAccessDataSources
{
    /**
     * 
     * @return string
     */
    public function getServiceName() : string;
    
    /**
     * 
     * @return ServiceParameterInterface[]
     */
    public function getParameters() : array;
    
    /**
     * 
     * @param string $name
     * @return ServiceParameterInterface
     */
    public function getArgument(string $name) : ServiceParameterInterface;
}