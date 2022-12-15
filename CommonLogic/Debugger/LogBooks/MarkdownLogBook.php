<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

class MarkdownLogBook implements LogBookInterface
{
    const INDENT = '  ';
    
    private $title = null;
    
    private $mermaid = false;
    
    private $lines = [];
    
    private $id = null;
    
    /**
     * 
     * @param string $title
     * @param string $defaultChapter
     * @param bool $enableMermaidDiagrams
     */
    public function __construct(string $title, string $defaultChapter = '')
    {
        $this->title = $title;
        $this->addChapter($defaultChapter);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLine()
     */
    public function addLine(string $text, int $indent = 0, string $chapter = null): LogBookInterface
    {
        $this->lines[$chapter ?? $this->getChapterCurrent()][] = ['indent' => $indent, 'text' => $text];
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLineSpacing()
     */
    public function addLineSpacing(string $chapter = null): LogBookInterface
    {
        $this->lines[$chapter ?? $this->getChapterCurrent()][] = ['indent' => 0, 'text' => ''];
        return $this;
    }
    
    public function addChapter(string $title) : LogBookInterface
    {
        $this->lines[$title] = [];
        return $this;
    }
    
    public function addCodeBlock(string $code, string $type = '', string $chapter = null) : LogBookInterface
    {
        if ($type === 'mermaid') {
            $this->enableMermaidDiagrams(true);
        }
        return $this->addLine(PHP_EOL . '```' . $type . PHP_EOL . $code . PHP_EOL . '```', 0, $chapter);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        // Add a tab with the data sheet UXON
        $tab = $debugWidget->createTab();
        $debugWidget->addTab($tab);
        $tab->setCaption($this->title);
        $tab->setColumnsInGrid(1);
        $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'Markdown',
            'value' => $this->toMarkdown(),
            'width' => 'max',
            'render_mermaid_diagrams' => $this->mermaid
        ])));
        
        return $debugWidget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Stringable::__toString()
     */
    public function __toString()
    {
        return $this->toMarkdown();
    }
    
    public function getId() : string
    {
        if ($this->id === null) {
            $this->id = uniqid();
        }
        return $this->id;
    }
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return MarkdownLogBook
     */
    protected function enableMermaidDiagrams(bool $trueOrFalse) : MarkdownLogBook
    {
        $this->mermaid = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        $str = '';
        foreach ($this->lines as $chapter => $lines) {
            if ($chapter !== '') {
                $str .= PHP_EOL . '## ' . $chapter . PHP_EOL;
            }
            foreach ($lines as $lineProps) {
                $str .= $this->convertIndentToString($lineProps['indent']) . $lineProps['text'] . PHP_EOL;
            }
        }
        return $str;
    }
    
    /**
     * 
     * @return string
     */
    protected function getChapterCurrent() : string
    {
        return array_key_last($this->lines) ?? '';
    }
    
    /**
     * 
     * @param int $indent
     * @return string
     */
    protected function convertIndentToString(int $indent) : string
    {
        $str = '';
        for ($i = 0; $i < $indent; $i++) {
            $str .= self::INDENT;
        }
        return $str;
    }
}