<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Console;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\Interfaces\Widgets\iHaveCaption;

/**
 * Configuration for resources (people, rooms, etc.) in calendar-related data widgets.
 * 
 * IDEA resources typically are represented by a different meta object, than calendar items.
 * Perhaps, it would be better to make the resource a widget, so that it can be selected,
 * maybe have actions, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class ConsoleCommandPreset implements WidgetPartInterface, iHaveCaption
{
    use ImportUxonObjectTrait;
    use iHaveCaptionTrait;
    
    private $console = null;
    
    private $hint = null;
    
    private $commands = [];
    
    public function __construct(Console $consoleWidget)
    {
        $this->console = $consoleWidget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'title' => $this->getTitleColumn()->getAttributeAlias()
        ]);
        
        if ($this->hasSubtitle()) {
            $uxon->setProperty('subtitle', $this->getSubtitleColumn()->getAttributeAlias());
        }
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->console;
    }
    
    /**
     * 
     * @return Console
     */
    public function getConsole() : Console
    {
        return $this->console;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getWidget()->getWorkbench();
    }
    
    /**
     * 
     * @return string
     */
    public function getHint() : string
    {
        return $this->hint;
    }
    
    /**
     * 
     * @param string $value
     * @return ConsoleCommandPreset
     */
    public function setHint(string $value) : ConsoleCommandPreset
    {
        $this->hint = $this->evaluatePropertyExpression($value);
        return $this;
    }
    
    /**
     * 
     * @param UxonObject $array
     * @return ConsoleCommandPreset
     */
    public function setCommands(UxonObject $array) : ConsoleCommandPreset
    {
        $this->commands = $array->toArray();
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getCommands() : array
    {
        return $this->commands;
    }
    
    public function hasPlaceholders() : bool
    {
        return empty($this->getPlaceholders()) === false;
    }
    
    public function getPlaceholders() : array
    {
        $phs = [];
        foreach ($this->getCommands() as $command) {
            $phs = array_merge($phs, $this->findPlaceholders($command));
        }
        return $phs;
    }
    
    protected function findPlaceholders($string) : array
    {
        $placeholders = array();
        preg_match_all("/<[^<>]+>/", $string, $placeholders);
        return is_array($placeholders[0]) ? $placeholders[0] : array();
    }
}