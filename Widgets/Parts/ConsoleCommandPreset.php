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
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;


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
     * Commands can contain `<placeholders>` for arguments and options as known from
     * all sorts of command-line tool documentation - these will result in input
     * promts before the command is activated. 
     * 
     * Commands may also contain placeholders with static formulas: e.g. `[#=User('USERNAME')#]`
     * to get the username of the currently logged in user.
     * 
     * @uxon-property commands
     * @uxon-type array
     * @uxon-template [""]
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
        $commands = [];
        foreach ($this->commands as $command) {
            $phVals = [];
            foreach (StringDataType::findPlaceholders($command) as $ph) {
                if (Expression::detectFormula($ph) === true) {
                    $exp = ExpressionFactory::createFromString($this->getWorkbench(), $ph);
                    if (! $exp->isStatic()) {
                        throw new WidgetConfigurationError($this->getConsole(), 'Cannot use non-static expression "[#' . $ph . '#]" in console command presets!');
                    }
                    $phVals[$ph] = $exp->evaluate();
                }
            }
            if (! empty($phVals)) {
                $command = StringDataType::replacePlaceholders($command, $phVals);
            }
            $commands[] = $command;
        }
        return $commands;
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
     * @return string[]
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
        // CLI placeholders cannot include @ in order to distinguish between `<placeholder>` and `<email@domain.com>`
        preg_match_all("/<[^<>@]+>/", $string, $placeholders);
        return is_array($placeholders[0]) ? $placeholders[0] : array();
    }
}