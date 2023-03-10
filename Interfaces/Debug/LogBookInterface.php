<?php
namespace exface\Core\Interfaces\Debug;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface LogBookInterface extends iCanGenerateDebugWidgets, \Stringable
{
    /**
     * 
     * @param string $text
     * @param int|null $indent
     * @param string|int $section
     * @return LogBookInterface
     */
    public function addLine(string $text, int $indent = null, $section = null) : LogBookInterface;
    
    /**
     * 
     * @param string|int $section
     * @return LogBookInterface
     */
    public function addLineSpacing($section = null) : LogBookInterface;
    
    /**
     * 
     * @param string|int $title
     * @return LogBookInterface
     */
    public function addSection(string $title) : LogBookInterface;
    
    /**
     * 
     * @param string|int $section
     * @return LogBookInterface
     */
    public function setSectionActive($section) : LogBookInterface;
    
    /**
     * 
     * @param string $title
     * @return LogBookInterface
     */
    public function removeSection(string $title) : LogBookInterface;
    
    /**
     * 
     * @param int $zeroOrMore
     * @return LogBookInterface
     */
    public function setIndentActive(int $zeroOrMore) : LogBookInterface;
    
    /**
     * 
     * @param string $code
     * @param string|int $type
     * @return LogBookInterface
     */
    public function addCodeBlock(string $code, string $type = '', $section = null) : LogBookInterface;
    
    /**
     * 
     * @param \Throwable $e
     * @param int $indent
     * @return LogBookInterface
     */
    public function addException(\Throwable $e, int $indent = null) : LogBookInterface;
    
    /**
     * 
     * @return string
     */
    public function getId() : string;
}