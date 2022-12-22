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
    public function __construct(string $title, ActionInterface $action, TaskInterface $task, string $defaultSection = '')
    {
        $this->task = $task;
        $this->action = $action;
        if ($defaultSection === '') {
            $defaultSection = 'Action ' . $action->getAliasWithNamespace();
        }
        $this->logBook = new DataLogBook($title, $defaultSection);
        $this->logBook->addLine('Prototype class: ' . get_class($action));
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
    public function addLine(string $text, int $indent = 0, $section = null): LogBookInterface
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
        $this->logBook->addCodeBlock($code, $type);
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
}