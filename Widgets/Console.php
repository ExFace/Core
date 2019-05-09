<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\ConsoleCommandPreset;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\RuntimeException;

class Console extends AbstractWidget
{
    private $allowedCommands = [];
    
    private $workingDirectoryPath = null;
    
    private $startCommands = [];
    
    private $commandPresets = [];
    
    private $commandPresetsUxon = null;
    
    private $commandTimeout = null;
    
    private $environmentVars = [];
    
    private $workingDirectorySubfolder = null;
    
    private $workingDirectoyAttributeAlias = '';
    
    
    
    
    /**
     * Returns Array of regular expressions with allowed commands
     * 
     * @return array
     */
    public function getAllowedCommands() :array
    {
        return $this->allowedCommands;
    }
    
    /**
     * Array of regular expressions to check if a command is allowed
     * 
     * @uxon-proberty allowed_commands
     * @uxon-type object
     * 
     * @param UxonObject $uxon
     * @return Console
     */
    public function setAllowedCommands(UxonObject $uxon) : Console
    {
        $this->allowedCommands = $uxon->toArray();
        return $this;
    }
    
    
    /**
     * Returns the working directory path for the console relative to the installation folder.
     * 
     * @param string $pathRelativeToSubfolder
     * @return string
     */
    protected function buildWorkingDirectoryPath(string $pathRelativeToSubfolder) : string
    {
        $baseFolder = $this->getWorkbench()->filemanager()->getPathToBaseFolder();
        $workingFolder = Filemanager::pathJoin([$this->getWorkingDirectorySubfolder() ?? '', $pathRelativeToSubfolder]);
        if (is_dir(Filemanager::pathJoin([$baseFolder, $workingFolder])) === false) {
            throw new RuntimeException('Working Directory is not a folder!');
        }
        return $workingFolder;
    }
    
    /**
     * Sets a static path to the working directory for the console.
     *
     * The path can be either static or relative to the default folder of the plattform.
     *
     * @uxon-property working_directory_path
     * @uxon-type string
     *
     * @param string $pathRelativeToSubfolder
     * @return Console
     */
    public function setWorkingDirectoryPath(string $pathRelativeToSubfolder) : Console
    {
        $this->workingDirectoryPath = $pathRelativeToSubfolder;
        return $this;
    }
    
    /**
     * Returns the path to the working directory of the console terminal relative to the installation folder.
     * @return string
     */
    public function getWorkingDirectoryPath() : ?string
    {
        if ($this->workingDirectoryPath !== null) {
            return $this->buildWorkingDirectoryPath($this->workingDirectoryPath);
        }
        
        if ($this->isWorkingDirectoryBoundToAttribute() === true) {
            $prefillData = $this->getPrefillData();
            if ($prefillData && $col = $prefillData->getColumns()->getByAttribute($this->getWorkingDirectoryAttribute())) {
                $path = $col->getCellValue(0);
                return $this->buildWorkingDirectoryPath($path);
            }
        }
        return $this->buildWorkingDirectoryPath('');
    } 
    
    /**
     *
     * @return string|NULL
     */
    protected function getWorkingDirectorySubfolder() : ?string
    {
        return $this->workingDirectorySubfolder;
    }
    
    /**
     * Path between the installation folder and the path in working_directory or working_directory_attribute_alias.
     *
     * E.g. `vendor` if you use folder paths relative to the vendor folder.
     *
     * @uxon-property working_directory_subfolder
     * @uxon-type string
     *
     * @param string $pathRelativeToInstallationBase
     * @return Console
     */
    public function setWorkingDirectorySubfolder(string $pathRelativeToInstallationBase) : Console
    {
        $this->workingDirectorySubfolder = $pathRelativeToInstallationBase;
        return $this;
    }
    
    /**
     * Returns array of commands to be performed when widget is loaded
     * 
     * @return array
     */
    public function getStartCommands() : array
    {
        return $this->startCommands;    
    }
    
    /**
     * Array of commands to be performed when the widget is loaded.
     * 
     * @uxon-proberty start_commands
     * @uxon-type object
     * @param UxonObject $uxon
     * @return Console
     */
    public function setStartCommands(UxonObject $uxon) : Console
    {
        $commands = $uxon->toArray();
        $this->startCommands = $commands;
        foreach ($commands as $command) {
            $this->allowedCommands[] = '/' . preg_quote($command, '/') . '/';
        }
        return $this;
    }
    
    /**
     * Add command presets - buttons or menu items, that perform a predefined set of commands.
     * 
     * Example:
     * 
     * ```
     * [
     *  {
     *      "caption": "Commit all",
     *      "hint": "Performs a git commit for all current changes with a default message",
     *      "commands": [
     *          "git add --all",
     *          "git commit"
     *      ]
     *  },
     *  {
     *      "caption": "=TRANSLATE('my.App', 'BTN_SHOW_CHANGES')",
     *      "hint": "=TRANSLATE('my.App', 'BTN_SHOW_CHANGES_HINT')",
     *      "commands": [
     *          "git status"
     *      ]
     *  }
     * ]
     * 
     * ```
     * 
     * @param UxonObject $uxon
     * @return Console
     */
    public function setCommandPresets(UxonObject $uxon) : Console
    {
        $this->commandPresetsUxon = $uxon;
        $this->commandPresets = [];
        return $this;
    }
    
    /**
     * Return array containing command presets, that perform a predefined set of commands
     * 
     * @return ConsoleCommandPreset[]
     */
    public function getCommandPresets() : array
    {
        if (empty($this->commandPresets) && $this->commandPresetsUxon !== null) {
            foreach ($this->commandPresetsUxon as $presetUxon) {
                $preset = new ConsoleCommandPreset($this);
                $preset->importUxonObject($presetUxon);
                $this->commandPresets[] = $preset;
            }
        }
        return $this->commandPresets;
    }
    
    /**
     * Check if Widget has command presets
     * 
     * @return bool
     */
    public function hasCommandPresets() : bool
    {
        return empty($this->commandPresets) === false || ($this->commandPresetsUxon !== null && $this->commandPresetsUxon->isEmpty() === false);
    }
        
    /**
     * Timeout for the commands in seconds
     * 
     * @uxon-proberty command_timeout
     * @uxon-type string
     * 
     * @param string $timeout
     * @return Console
     */
    public function setCommandTimeout(string $timeout) : Console
    {
        $this->commandTimeout = floatval($timeout);
        return $this;
    }
    
    /**
     * Return the Timeout for the commands in seconds
     * 
     * @return float
     */
    public function getCommandTimeout() : float
    {
        if ($this->commandTimeout != null){
            return $this->commandTimeout;
        }
        return 600;
    }
    
    
    /**
     * Array of Environment Variables to be used executing the commands
     * 
     * @uxon-object environment_variables
     * @uxon-type object
     * @param UxonObject $uxon
     * @return Console
     */
    public function setEvironmentVars(UxonObject $uxon) : Console
    {
        $this->environmentVars = $uxon->toArray();
        return $this;
    }
    
    /**
     * Returns array of Environment Variables to be used executing the commands
     * 
     * @return array
     */
    public function getEnvironmentVars() : array
    {
        return $this->environmentVars;
    }
    
    /**
     *
     * @return string
     */
    protected function getWorkingDirectoryAttributeAlias() : string
    {
        return $this->workingDirectoryAttributeAlias;
    }
    
    /**
     *
     * @return bool
     */
    protected function isWorkingDirectoryBoundToAttribute() : bool
    {
        return $this->workingDirectoryAttributeAlias !== null;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    protected function getWorkingDirectoryAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getWorkingDirectoryAttributeAlias());
    }
    
    /**
     * Alias of the attribute, that holds the relative or absolute path to the folder to zip.
     *
     * The path can be either static or relative to the installation folder of the plattform.
     *
     * @uxon-property working_directory_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return Console
     */
    public function setWorkingDirectoryAttributeAlias(string $value) : Console
    {
        $this->workingDirectoryAttributeAlias = $value;
        return $this;
    }
}