<?php
namespace exface\Core\Interfaces\Api;

interface TaskResultTextInterface extends TaskResultInterface
{
    /**
     * 
     * @return string
     */
    public function getText() : string;    
}