<?php
namespace exface\Core\Exceptions;

use exface\Core\DataTypes\StringDataType;

/**
 * Exception thrown when exec() calls fail.
 * 
 * Usage:
 * 
 * ```
 *  exec('some command', $output, $returnVar);
 *  if ($returnVar === 1) {
 *      throw new CliExecException('some command', $output);
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class CliExecException extends RuntimeException
{
    const TYPE_ERROR = 'ERROR';
    
    const TYPE_WARNING = 'WARNING';
    
    const TYPE_SUCCESS = 'SUCCESS';
    
    private $output = null;
    
    private $command = null;
    
    private $type = null;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
     */
    public function __construct(string $command, $output, $alias = null, $previous = null)
    {        
        if (is_array($output)) {
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
                    case StringDataType::startsWith($line, self::TYPE_ERROR . ': '):
                        $this->type = self::TYPE_SUCCESS;
                        $message = $line;
                        break; // do not break the foreach() here - keep looking for errors/warnings
                }
            }
            $this->output = $output;
        } else {
            $message = $output;
            $this->output = [$output];
        }
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->command = $command;
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
}