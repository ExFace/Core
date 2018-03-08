<?php
namespace exface\Core\Interfaces\Tasks;

interface TaskResultStreamInterface extends TaskResultInterface
{
    /**
     * 
     * @return string
     */
    public function getMimeType() : string; 
    
    /**
     * 
     * @param string $string
     * @return TaskResultStreamInterface
     */
    public function setMimeType(string $string) : TaskResultStreamInterface;
}