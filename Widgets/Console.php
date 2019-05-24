<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\ConsoleCommandPreset;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;

/**
 * Shows a command line terminal.
 * 
 * Example:
 * 
 * ```
 * {
 *  "widget_type": "Console",
 *  "allowed_commands": [
 *      "@cd .*@",
 *      "@git .*@"
 *  ],
 *  "start_commands": [
 *      "git status"
 *  ],
 *  "environment_vars": {
 *    "GIT_SSL_NO_VERIFY": true,
 *    "GIT_COMMITTER_NAME": "=User('full_name')",
 *    "GIT_COMMITTER_EMAIL": "=User('email')"
 *  },
 * 	"command_prsets": [
 * 		{
 * 			"caption": "Commit/Push all",
 * 			"hint": "Commits all local changes and pushes them to the current remote",
 * 			"commands": [
 * 				"git commit -a -m <message>",
 * 				"git push"
 * 			]
 * 		},
 * 		{
 * 			"caption": "List branches",
 * 			"commands": [
 * 				"git branch -a"
 * 			]
 * 		},
 * 		{
 * 			"caption": "Switch branch",
 * 			"commands": [
 * 				"git checkout <branch>"
 * 			]
 * 		}
 * 	]
 * }
 * 
 * ```
 * 
 * @author Ralf Mulansky
 *
 */
class Console extends AbstractWidget
{
    use TranslatablePropertyTrait;
    
    private $allowedCommands = [];
    
    private $workingDirectoryPath = null;
    
    private $startCommands = [];
    
    private $commandPresets = [];
    
    private $commandPresetsUxon = null;
    
    private $commandTimeout = 600;
    
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
     * Set array of regular expressions to check if a command is allowed.
     * 
     * Each command is treated as aregular expression, so take care of placing it
     * between valid regex delimiters: e.g. type `/whoami/` to allow the `whoami`
     * command (the `/` would be the regex delimiter).
     * 
     * @uxon-property allowed_commands
     * @uxon-type array
     * @uxon-template [""]
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
     * Builds the working directory path for the console relative to the installation folder.
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
     * Sets a path to the working directory for the console relative to the installation folder.
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
     *
     * @return string
     */
    public function getWorkingDirectoryPath() : string
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
     * Returns path between the installation folder and the path in working_directory_path or working_directory_attribute_alias.
     * 
     * @return string
     */
    protected function getWorkingDirectorySubfolder() : string
    {
        return $this->workingDirectorySubfolder ?? '';
    }
    
    /**
     * Path between the installation folder and the path in working_directory_path or working_directory_attribute_alias.
     *
     * E.g. `vendor` if you use folder paths relative to the vendor folder.
     *
     * @uxon-property working_directory_subfolder
     * @uxon-type string
     * @uxon-default ''
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
     * Set array of commands to be performed when the widget is loaded.
     * 
     * @uxon-property start_commands
     * @uxon-type array
     * @uxon-template [""]
     * 
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
     * Each preset has caption, hint, visibilty and a set of commands, it performs. The commands
     * may include palceholders (e.g. `<message>`). If so, the user will be asked to provide
     * values for every placeholder once the preset is activated.
     * 
     * Captions and hints support static formulas like `=TRANSLATE()`.
     * 
     * Example:
     * 
     * ```
     * [
     *  {
     *      "caption": "Commit all",
     *      "hint": "Performs a git commit for all current changes with a default message",
     *      "visibility": "promoted",
     *      "commands": [
     *          "git add --all",
     *          "git commit -m <message>"
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
     * @uxon-property command_presets
     * @uxon-type \exface\Core\Widgets\Parts\ConsoleCommandPreset[]
     * @uxon-template [{"caption": "", "hint": "", "commands": [""]}]
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
     * Check if widget has command presets
     * 
     * @return bool
     */
    public function hasCommandPresets() : bool
    {
        return empty($this->commandPresets) === false || ($this->commandPresetsUxon !== null && $this->commandPresetsUxon->isEmpty() === false);
    }
        
    /**
     * Set timeout for the commands in seconds.
     * Default is 600.
     * 
     * @uxon-property command_timeout
     * @uxon-type integer
     * @uxon-default 600
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
     * Return the Timeout for the commands in seconds.
     * To deactivate timeout set it to '0.0'.
     * 
     * @return float
     */
    public function getCommandTimeout() : float
    {
        return 600;
    }
    
    
    /**
     * Environment variables to be used when executing commands as key-value-pairs.
     * 
     * Static formulas like `=User('username')` are supported in values.
     * 
     * @uxon-property environment_vars
     * @uxon-type object
     * @uxon-template {"VAR_NAME": "value"}
     * 
     * @param UxonObject $uxon
     * @return Console
     */
    public function setEnvironmentVars(UxonObject $uxon) : Console
    {
        foreach ($uxon->toArray() as $var => $val) {
            $this->environmentVars[$var] = $this->evaluatePropertyExpression($val);
        }
        return $this;
    }
    
    /**
     * Returns array of environment variables to be used executing the commands
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
     * Alias of the attribute, that holds the relative path from the installation folder to the working directory.
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