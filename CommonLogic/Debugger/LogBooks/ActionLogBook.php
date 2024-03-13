<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Widgets\Tabs;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;

class ActionLogBook implements DataLogBookInterface
{
    private $task = null;
    
    private $action = null;
    
    private $logBook = null;
    
    private $autoSectionsAdded = false;
    
    private $flowDiagram = null;

    /**
     * 
     * @param string $title
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @param string $defaultSection
     */
    public function __construct(string $title, ActionInterface $action, TaskInterface $task)
    {
        $this->task = $task;
        $this->action = $action;
        $this->logBook = new DataLogBook($title);
        $this->logBook->addSection('Action ' . $action->getAliasWithNamespace());
        $this->logBook->addLine('Prototype class: ' . get_class($action));
        try {
            $this->logBook->addLine('Action object: ' . $action->getMetaObject()->__toString());
        } catch (\Throwable $e) {
            $this->logBook->addLine('Action object not found');
        }
    }
    
    /**
     * 
     * @return TaskInterface
     */
    public function getTask() : TaskInterface
    {
        return $this->task;
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
        
    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        return $this->logBook->__toString();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLine()
     */
    public function addLine(string $text, int $indent = null, $section = null): LogBookInterface
    {
        $this->logBook->addLine($text, $indent, $section);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->logBook->createDebugWidget($debug_widget);
        $tabs = $debug_widget->getWidgetFirst()->getWidgetFirst();
        if ($tabs instanceof Tabs) {
            $tab = $tabs->createTab();
            $tab->setCaption('Action config');
            $tabs->addTab($tab);
            $tab->addWidget(WidgetFactory::createFromUxonInParent($tabs, new UxonObject([
                'widget_type' => 'InputUxon',
                'width' => 'max',
                'height' => '100%',
                'caption' => PhpClassDataType::findClassNameWithoutNamespace(get_class($this->action)),
                'hide_caption' => true,
                'value' => $this->action->exportUxonObject()->toJson(true),
                'root_prototype' => '\\' . get_class($this->action)
            ])));
        }
        return $debug_widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\DataLogBookInterface::addDataSheet()
     */
    public function addDataSheet(string $title, DataSheetInterface $dataSheet): LogBookInterface
    {
        $this->logBook->addDataSheet($title, $dataSheet);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addSection()
     */
    public function addSection(string $title): LogBookInterface
    {
        $this->logBook->addSection($title);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::setSectionActive()
     */
    public function setSectionActive($section) : LogBookInterface
    {
        $this->logBook->setSectionActive($section);
        return $this;
    }
    
    public function getSectionActive() : ?string
    {
        return $this->logBook->getSectionActive();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::removeSection()
     */
    public function removeSection(string $title): LogBookInterface
    {
        $this->logBook->removeSection($title);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLineSpacing()
     */
    public function addLineSpacing($section = null): LogBookInterface
    {
        $this->logBook->addLineSpacing($section);
        return $this;
    }
    
    /**
     * 
     * @param string $code
     * @param string $type
     * @return MarkdownLogBook
     */
    public function addCodeBlock(string $code, string $type = '', $section = null) : LogBookInterface
    {
        $this->logBook->addCodeBlock($code, $type, $section);
        return $this;
    }
    
    /**
     * 
     * @param string $mermaid
     * @param string $placeInSection
     * @return ActionLogBook
     */
    public function setFlowDiagram(string $mermaid) : ActionLogBook
    {
        $this->flowDiagram = $mermaid;
        $this->logBook->addCodeBlock($mermaid, 'mermaid', 1);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFlowDiagram() : ?string
    {
        return $this->flowDiagram;
    }
    
    /**
     * 
     * @return string
     */
    public function getFlowDiagramStyleError() : string
    {
        return "fill:#FF6347,stroke:#FF0000";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getId()
     */
    public function getId(): string
    {
        return $this->logBook->getId();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\DataLogBookInterface::getDataSheets()
     */
    public function getDataSheets(): array
    {
        return $this->logBook->getDataSheets();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::setIndentActive()
     */
    public function setIndentActive(int $zeroOrMore) : LogBookInterface
    {
        $this->logBook->setIndentActive($zeroOrMore);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addIndent()
     */
    public function addIndent(int $positiveOrNegative) : LogBookInterface
    {
        $this->logBook->addIndent($positiveOrNegative);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addException()
     */
    public function addException(\Throwable $e, int $indent = null) : LogBookInterface
    {
        $this->logBook->addException($e, $indent);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addPlaceholderValue()
     */
    public function addPlaceholderValue(string $placeholder, string $value): LogBookInterface
    {
        $this->logBook->addPlaceholderValue($placeholder, $value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::removeLine()
     */
    public function removeLine($section, int $lineNo): LogBookInterface
    {
        $this->logBook->removeLine($section, $lineNo);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getCodeBlocksInSection()
     */
    public function getCodeBlocksInSection($section = null): array
    {
        return $this->logBook->getCodeBlocksInSection($section);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getLinesInSection()
     */
    public function getLinesInSection($section = null): array
    {
        return $this->logBook->getLinesInSection($section);
    }
}