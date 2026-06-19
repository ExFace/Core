<?php
namespace exface\Core\Exceptions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\DebugMessage;

/**
 * Exception thrown when CLI commands fail.
 * 
 * Usage:
 * 
 * ```
 *  $exitCode = 0;
 *  $output = [];
 *  exec('some command', $output, $exitCode);
 *  if ($exitCode !== 0) {
 *      throw new CliRuntimeException('some command', $output, $exitCode);
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class CliRuntimeException extends RuntimeException
{
    const TYPE_ERROR = 'ERROR';
    
    const TYPE_WARNING = 'WARNING';
    
    const TYPE_SUCCESS = 'SUCCESS';
    
    
    private string $command;
    private array $output;
    private ?int $exitCode = null;
    
    private $type = null;

    /**
     * @param string $command
     * @param string|array|null $output
     * @param int|null $exitCode
     * @param string|null $message
     * @param null $alias
     * @param null $previous
     */
    public function __construct(string $command, array|string|null $output, ?int $exitCode = null, ?string $message = null, $alias = null, $previous = null)
    {
        $this->command = $command;
        $this->exitCode = $exitCode;
        
        if (is_array($output)) {
            $this->output = $output;
            if ($message === null) {
                foreach ($output as $line) {
                    switch (true) {
                        case StringDataType::startsWith($line, self::TYPE_ERROR . ': '):
                            $this->type = self::TYPE_ERROR;
                            $message = $line;
                            break 2;
                        case StringDataType::startsWith($line, self::TYPE_WARNING . ': '):
                            $this->type = self::TYPE_WARNING;
                            $message = $line;
                            break 2;
                        case StringDataType::startsWith($line, self::TYPE_SUCCESS . ': '):
                            $this->type = self::TYPE_SUCCESS;
                            $message = $line;
                            break; // do not break the foreach() here - keep looking for errors/warnings
                    }
                }
            }
            
            if(empty($message)) {
                $message = $output[0] ?? 'CLI command `' . $command . '` failed with exit code `' . $exitCode . '`.';
                $this->type = self::TYPE_ERROR;
            }
        } else {
            $this->output = [$output];
            // TODO search for the error here???
            $message = $message ?? 'CLI command `' . $command . '` failed with exit code `' . $exitCode . '`.';
        }
        parent::__construct($message, $alias, $previous);
    }

    /**
     * 
     * @return string
     */
    public function getCommand() : string
    {
        return $this->command;
    }
    
    /**
     * Returns lines of the command output as an array
     * 
     * @return string[]
     */
    public function getCliOutput() : array
    {
        return $this->output;
    }
    
    /**
     * Returns the output type: SUCCESS, WARNING, ERROR or NULL if unknown.
     * 
     * @return string|NULL
     */
    public function getType() : ?string
    {
        return $this->type;
    }
    
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = parent::createDebugWidget($debug_widget);
        
        $tab = $debug_widget->createTab();
        $tab->setCaption('CLI');
        $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'Markdown',
            'value' => $this->buildMarkdown()
        ])));
        $debug_widget->addTab($tab);
        
        return $debug_widget;
    }
    
    protected function buildMarkdown() : string
    {
        $cliContent = '> ' . $this->getCommand();
        $cliContent .= "\n\n" . implode("\n", $this->getCliOutput());
        return MarkdownDataType::escapeCodeBlock($cliContent, 'bash');
    }
}