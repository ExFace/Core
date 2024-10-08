<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Exceptions\InternalError;

class MarkdownLogBook implements LogBookInterface
{
    const INDENT = '  ';
    
    private $title = null;
    
    private $mermaid = false;
    
    private $lines = [];
    
    private $id = null;
    
    private $currentSection = null;
    
    private $currentIndent = 0;
    
    private $placeholders = [];    
    
    private $exceptions = [];
    
    /**
     * 
     * @param string $title
     * @param string $defaultSection
     * @param bool $enableMermaidDiagrams
     */
    public function __construct(string $title)
    {
        $this->title = $title;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLine()
     */
    public function addLine(string $text, int $indent = null, $section = null): LogBookInterface
    {
        $this->lines[$this->getSectionKey($section)][] = ['indent' => $this->currentIndent + ($indent ?? 0), 'text' => $text];
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
        $this->currentSection = $title;
        $this->setIndentActive(0);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::setSectionActive()
     */
    public function setSectionActive($section) : LogBookInterface
    {
        $this->currentSection = $this->getSectionKey($section);
        return $this;
    }
    
    public function getSectionActive() : ?string
    {
        return $this->currentSection;
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
        
        // Render added exceptions as separate tabs with all their data (other debug tabs)
        // inside
        foreach ($this->exceptions as $i => $e) {
            if (! ($e instanceof iCanGenerateDebugWidgets)) {
                $e = new InternalError($e->getMessage(), null, $e);
            }
            $errorTab = $debugWidget->createTab();
            $errorTab->setCaption($this->getExceptionTitle($i));
            $debugWidget->addTab($errorTab);
            $errorWidget = WidgetFactory::createDebugMessage($debugWidget->getWorkbench());
            // Make sure each nested error debug widget has its own id space as tabs
            // generated by exceptions seem to have fixed ids
            $errorWidget->setIdSpace($errorWidget->getId());
            $errorWidget = $e->createDebugWidget($errorWidget);
            $errorTab->addWidget($errorWidget);
        }
        
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
                $indent = $lineProps['indent'];
                if ($indent === 0) {
                    $str .= PHP_EOL;
                }
                // Make ordinary text lines list items and indent code blocks
                if ($this->isCodeBlock($lineProps['text']) === true) {
                    // A code block normally starts with a line break (and maybe an indent),
                    // so to avoid multiple line breaks breaking list strukture, we remove the line
                    // break of the previous line before attaching the code block.
                    $str = rtrim($str) . $this->indentLines($indent, $lineProps['text']) . PHP_EOL;
                } else {
                    $str .= $this->convertIndentToString($lineProps['indent']) . $lineProps['text'] . PHP_EOL;
                }
            }
        }
        if (! empty($this->placeholders)) {
            $str = StringDataType::replacePlaceholders($str, $this->placeholders, false);
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
            return $this->currentSection ?? array_key_last($this->lines) ?? '';
        }
        if (is_int($section)) {
            if ($section === 0) {
                $section = 1;
            }
            $section = array_keys($this->lines)[$section - 1] ?? $this->currentSection;
        }
        $this->currentSection = $section;
        return $section;
    }
    
    /**
     * 
     * @param int $indent
     * @return string
     */
    protected function convertIndentToString(int $indent, bool $makeListItem = true) : string
    {
        if ($indent === 0) {
            return '';
        }
        $str = '';
        for ($i = 1; $i < $indent; $i++) {
            $str .= self::INDENT;
        }
        return $str . ($makeListItem === true ? '- ' : '');
    }

    protected function indentLines(int $indent, string $multiLineString) : string
    {
        $lines = StringDataType::splitLines($multiLineString);
        foreach ($lines as $i => $line) {
            $lines[$i] = $this->convertIndentToString($indent, false) . $line;
        }
        return implode(PHP_EOL, $lines);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::setIndentActive()
     */
    public function setIndentActive(int $zeroOrMore) : LogBookInterface
    {
        $this->currentIndent = $zeroOrMore;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addIndent()
     */
    public function addIndent(int $positiveOrNegative) : LogBookInterface
    {
        $this->currentIndent = max($this->currentIndent + $positiveOrNegative, 0);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addException()
     */
    public function addException(\Throwable $e, int $indent = null) : LogBookInterface
    {
        $this->addLine('**Exception** "' . $e->getMessage() . '" in '. $e->getFile() . ' on line ' . $e->getLine() . ' - see debug tab "' . $this->getExceptionTitle(count($this->exceptions)) . '"', $indent);
        $this->exceptions[] = $e;
        return $this;
    }
    
    protected function getExceptionTitle(int $idx) : string
    {
        return 'Error ' . ($idx + 1);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getLines()
     */
    public function getLinesInSection($section = null): array
    {
        $sectionKey = $this->getSectionKey($section);
        $lines = [];
        foreach ($this->lines[$sectionKey] as $i => $line) {
            $lines[$i] = $line['text'];
        }
        return $lines;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addPlaceholderValue()
     */
    public function addPlaceholderValue(string $placeholder, string $value): LogBookInterface
    {
        $this->placeholders[$placeholder] = $value;
        return $this;
    }
    
    public function getPlaceholderValue(string $placeholder) : ?string
    {
        return $this->placeholders[$placeholder] ?? null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getCodeBlocksInSection()
     */
    public function getCodeBlocksInSection($section = null): array
    {
        $blocks = [];
        foreach ($this->getLinesInSection($section) as $no => $line) {
            if ($this->isCodeBlock($line)) {
                $blocks[$no] = $line;
            }
        }
        return $blocks;
    }
    
    /**
     * 
     * @param string $line
     * @return bool
     */
    protected function isCodeBlock(string $line) : bool
    {
        return mb_substr(trim($line), 0, 3) === '```';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::removeLine()
     */
    public function removeLine($section, int $lineNo): LogBookInterface
    {
        $lines = $this->lines[$this->getSectionKey($section)];
        unset($lines[$lineNo]);
        $this->lines[$this->getSectionKey($section)] = array_values($lines);
        return $this;
    }
}