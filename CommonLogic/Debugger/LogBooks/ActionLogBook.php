<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DebugMessage;

class ActionLogBook implements DataLogBookInterface
{
    private $task = null;
    
    private $action = null;
    
    private $logBook = null;
    
    private $autoChaptersAdded = false;
    
    private $flowDiagram = null;

    public function __construct(string $title, ActionInterface $action, TaskInterface $task, string $defaultChapter = '')
    {
        $this->task = $task;
        $this->action = $action;
        if ($defaultChapter === '') {
            $defaultChapter = 'Logbook for action ' . $action->getAliasWithNamespace();
        }
        $this->logBook = new DataLogBook($title, $defaultChapter);
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
    public function addLine(string $text, int $indent = 0, string $chapter = null): LogBookInterface
    {
        $this->logBook->addLine($text, $indent, $chapter);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        if ($this->autoChaptersAdded === false) {
            $this->autoChaptersAdded = true;
            $this->logBook->addCodeBlock($this->action->exportUxonObject()->toJson(true), 'json');
            $this->logBook->addChapter('Action configuration');
            $this->logBook->addCodeBlock($this->action->exportUxonObject()->toJson(true), 'json');
        }
        return $this->logBook->createDebugWidget($debug_widget);
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
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addChapter()
     */
    public function addChapter(string $title): LogBookInterface
    {
        $this->logBook->addChapter($title);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLineSpacing()
     */
    public function addLineSpacing(string $chapter = null): LogBookInterface
    {
        $this->logBook->addLineSpacing($chapter);
        return $this;
    }
    
    /**
     * 
     * @param string $code
     * @param string $type
     * @return MarkdownLogBook
     */
    public function addCodeBlock(string $code, string $type = '', string $chapter = null) : LogBookInterface
    {
        $this->logBook->addCodeBlock($code, $type);
        return $this;
    }
    
    public function setFlowDiagram(string $mermaid, string $placeInChapter = null) : ActionLogBook
    {
        $this->flowDiagram = $mermaid;
        $this->logBook->addCodeBlock($mermaid, 'mermaid', $placeInChapter);
        return $this;
    }
    
    public function getFlowDiagram() : ?string
    {
        return $this->flowDiagram;
    }
    
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