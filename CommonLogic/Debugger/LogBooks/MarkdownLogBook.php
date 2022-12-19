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
     * @param string $defaultSection
     * @param bool $enableMermaidDiagrams
     */
    public function __construct(string $title, string $defaultSection = '')
    {
        $this->title = $title;
        $this->addSection($defaultSection);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLine()
     */
    public function addLine(string $text, int $indent = 0, $section = null): LogBookInterface
    {
        $this->lines[$this->getSectionKey($section)][] = ['indent' => $indent, 'text' => $text];
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLineSpacing()
     */
    public function addLineSpacing($section = null): LogBookInterface
    {
        $this->lines[$this->getSectionKey($section)][] = ['indent' => 0, 'text' => ''];
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addSection()
     */
    public function addSection(string $title) : LogBookInterface
    {
        $this->lines[$title] = [];
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::removeSection()
     */
    public function removeSection(string $title) : LogBookInterface
    {
        unset($this->lines[$title]);
        return $this;        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addCodeBlock()
     */
    public function addCodeBlock(string $code, string $type = '', $section = null) : LogBookInterface
    {
        if ($type === 'mermaid') {
            $this->enableMermaidDiagrams(true);
        }
        return $this->addLine(PHP_EOL . '```' . $type . PHP_EOL . $code . PHP_EOL . '```', 0, $section);
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
        foreach ($this->lines as $section => $lines) {
            if ($section !== '') {
                $str .= PHP_EOL . '## ' . $section . PHP_EOL;
            }
            foreach ($lines as $lineProps) {
                $str .= $this->convertIndentToString($lineProps['indent']) . $lineProps['text'] . PHP_EOL;
            }
        }
        return $str;
    }
    
    /**
     *
     * @param string|int $section
     * @return string
     */
    protected function getSectionKey($section = null) : string
    {
        if ($section === null) {
            return array_key_last($this->lines) ?? '';
        }
        if (is_int($section)) {
            return array_keys($this->lines)[$section - 1] ?? '';
        }
        return $section;
    }
    
    /**
     * 
     * @param int $indent
     * @return string
     */
    protected function convertIndentToString(int $indent) : string
    {
        if ($indent === 0) {
            return '';
        }
        $str = '';
        for ($i = 0; $i < $indent; $i++) {
            $str .= self::INDENT;
        }
        return $str . '- ';
    }
}