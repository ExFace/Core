<?php
namespace exface\Core\Interfaces\Debug;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface LogBookInterface extends iCanGenerateDebugWidgets, \Stringable
{
    /**
     * 
     * @param string $text
     * @param int $indent
     * @param string|int $section
     * @return LogBookInterface
     */
    public function addLine(string $text, int $indent = 0, $section = null) : LogBookInterface;
    
    /**
     * 
     * @param string|int $section
     * @return LogBookInterface
     */
    public function addLineSpacing($section = null) : LogBookInterface;
    
    /**
     * 
     * @param string $title
     * @return LogBookInterface
     */
    public function addSection(string $title) : LogBookInterface;
    
    /**
     * 
     * @param string $title
     * @return LogBookInterface
     */
    public function removeSection(string $title) : LogBookInterface;
    
    /**
     * 
     * @param string $code
     * @param string|int $type
     * @return LogBookInterface
     */
    public function addCodeBlock(string $code, string $type = '', $section = null) : LogBookInterface;
    
    /**
     * 
     * @return string
     */
    public function getId() : string;
}