<?php
namespace exface\Core\Interfaces\Tasks;

interface TaskResultTextInterface extends TaskResultInterface
{
    /**
     * 
     * @return string
     */
    public function getText() : string;    
}