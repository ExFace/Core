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
     * @param int $lineNo
     * @return LogBookInterface
     */
    public function removeLine($section, int $lineNo) : LogBookInterface;
    
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
     * @param int $positiveOrNegative
     * @return LogBookInterface
     */
    public function addIndent(int $positiveOrNegative) : LogBookInterface;
    
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
    
    /**
     * 
     * @param string|int $section
     * @return string[]
     */
    public function getLinesInSection($section = null) : array;
    
    /**
     * 
     * @param string|int $section
     * @return string[]
     */
    public function getCodeBlocksInSection($section = null) : array;
    
    /**
     * 
     * @param string $placeholder
     * @param string $value
     * @return LogBookInterface
     */
    public function addPlaceholderValue(string $placeholder, string $value) : LogBookInterface;
}