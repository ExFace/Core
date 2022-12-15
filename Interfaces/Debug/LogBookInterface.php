<?php
namespace exface\Core\Interfaces\Debug;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface LogBookInterface extends iCanGenerateDebugWidgets, \Stringable
{
    /**
     * 
     * @param string $text
     * @param int $indent
     * @param string $chapter
     * @return LogBookInterface
     */
    public function addLine(string $text, int $indent = 0, string $chapter = null) : LogBookInterface;
    
    /**
     * 
     * @param string $chapter
     * @return LogBookInterface
     */
    public function addLineSpacing(string $chapter = null) : LogBookInterface;
    
    /**
     * 
     * @param string $title
     * @return LogBookInterface
     */
    public function addChapter(string $title) : LogBookInterface;
    
    /**
     * 
     * @param string $code
     * @param string $type
     * @return LogBookInterface
     */
    public function addCodeBlock(string $code, string $type = '', string $chapter = null) : LogBookInterface;
    
    /**
     * 
     * @return string
     */
    public function getId() : string;
}