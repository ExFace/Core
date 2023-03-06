<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Widgets\Tabs;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Events\EventInterface;

class BehaviorLogBook implements DataLogBookInterface
{
    private $event = null;
    
    private $behavior = null;
    
    private $logBook = null;
    
    private $autoSectionsAdded = false;
    
    private $flowDiagram = null;

    /**
     * 
     * @param string $title
     * @param ActionInterface $behavior
     * @param EventInterface $event
     * @param string $defaultSection
     */
    public function __construct(string $title, BehaviorInterface $behavior, EventInterface $event, string $defaultSection = '')
    {
        $this->event = $event;
        $this->behavior = $behavior;
        if ($defaultSection === '') {
            $defaultSection = 'Behavior ' . PhpClassDataType::findClassNameWithoutNamespace($this);
        }
        $this->logBook = new DataLogBook($title, $defaultSection);
        $this->logBook->addLine('Prototype class: ' . get_class($behavior));
    }
    
    /**
     * 
     * @return EventInterface
     */
    public function getEvent() : EventInterface
    {
        return $this->event;
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
            $tab->setCaption('Behavior config');
            $tabs->addTab($tab);
            $tab->addWidget(WidgetFactory::createFromUxonInParent($tabs, new UxonObject([
                'widget_type' => 'InputUxon',
                'width' => 'max',
                'height' => '100%',
                'caption' => PhpClassDataType::findClassNameWithoutNamespace(get_class($this->behavior)),
                'hide_caption' => true,
                'value' => $this->behavior->exportUxonObject()->toJson(true),
                'root_prototype' => '\\' . get_class($this->behavior)
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
     * @return BehaviorLogBook
     */
    public function setFlowDiagram(string $mermaid) : BehaviorLogBook
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