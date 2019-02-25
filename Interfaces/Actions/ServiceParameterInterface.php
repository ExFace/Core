<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
interface ServiceParameterInterface extends  iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    /**
     *
     * @return string
     */
    public function getName() : string;
    
    /**
     *
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
    
    /**
     *
     * @return bool
     */
    public function isRequired() : bool;
    
    public function getAction() : iCallService;
    
    public function isValidValue($val) : bool;
}