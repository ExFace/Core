<?php
namespace exface\Core\Interfaces\Tasks;

interface ResultTextContentInterface extends ResultStreamInterface
{
    /**
     * 
     * @return string
     */
    public function getContent() : string;  
    
    /**
     * 
     * @param string $content
     * @return ResultTextContentInterface
     */
    public function setContent(string $content) : ResultTextContentInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasContent() : bool;
}