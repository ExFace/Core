<?php
namespace exface\Core\Interfaces\Tasks;

interface ResultStreamInterface extends ResultInterface
{
    /**
     * 
     * @return string
     */
    public function getMimeType() : string; 
    
    /**
     * 
     * @param string $string
     * @return ResultStreamInterface
     */
    public function setMimeType(string $string) : ResultStreamInterface;
}