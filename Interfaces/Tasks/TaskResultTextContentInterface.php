<?php
namespace exface\Core\Interfaces\Tasks;

interface TaskResultTextContentInterface extends TaskResultStreamInterface
{
    /**
     * 
     * @return string
     */
    public function getContent() : string;  
    
    /**
     * 
     * @param string $content
     * @return TaskResultTextContentInterface
     */
    public function setContent(string $content) : TaskResultTextContentInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasContent() : bool;
}