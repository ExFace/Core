<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Console;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Widgets\Traits\iHaveVisibilityTrait;
use exface\Core\Interfaces\Widgets\iHaveVisibility;


/**
 * Class to a handle console command preset being part of console widgets.
 * 
 * @author Ralf Mulansky
 * 
 */
class ConsoleCommandPreset implements WidgetPartInterface, iHaveCaption, iHaveVisibility
{
    use ImportUxonObjectTrait;
    use iHaveCaptionTrait;
    use iHaveVisibilityTrait;
    
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
     * Returns console widget the presets is part of
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
     * Returns hint for the preset
     * 
     * @return string
     */
    public function getHint() : string
    {
        return $this->hint;
    }
    
    /**
     * Set the hint for the preset.
     * 
     * Static formulas like `=TRANSLATE()` are supported.
     * 
     * @uxon-property hint
     * @uxon-type string|metamodel:formula
     * @uxon-translatable true
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
     * Set the commands for the preset
     * 
     * @param UxonObject $array
     * @return ConsoleCommandPreset
     */
    public function setCommands(UxonObject $array) : ConsoleCommandPreset
    {
        $this->commands = $array->toArray();
        // Placeholders (e.g. <message>) sometimes come with encoded html characters
        $this->commands = array_map('html_entity_decode', $this->commands);
        return $this;
    }
    
    /**
     * Returns the commands being part of the preset
     * 
     * @return array
     */
    public function getCommands() : array
    {
        return $this->commands;
    }
    
    /**
     * Returns true if the preset has placeholders.
     * Placeholders are parts of a command embraced by '<>'
     * 
     * @return bool
     */
    public function hasPlaceholders() : bool
    {
        return empty($this->getPlaceholders()) === false;
    }
    
    /**
     * Returns array containing all placeholders included in all commands of the preset
     * 
     * @return array
     */
    public function getPlaceholders() : array
    {
        $phs = [];
        foreach ($this->getCommands() as $command) {
            $phs = array_merge($phs, $this->findPlaceholders($command));
        }
        return $phs;
    }
    
    /**
     * Returns array containing every placeholder included in a command
     * 
     * @param string $string
     * @return array
     */
    protected function findPlaceholders(string $string) : array
    {
        $placeholders = array();
        preg_match_all("/<[^<>]+>/", $string, $placeholders);
        return is_array($placeholders[0]) ? $placeholders[0] : array();
    }
}