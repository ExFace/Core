<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\CliTaskInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class CliTask extends GenericTask implements CliTaskInterface
{    
    private $cliArguments = [];
    
    private $cliOptions = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliArguments()
     */
    public function getCliArguments() : array
    {
        return $this->cliArguments;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliArgument()
     */
    public function getCliArgument(string $name)
    {
        return $this->getCliArguments()[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::hasCliArgument()
     */
    public function hasCliArgument(string $name) : bool
    {
        return $this->getCliArguments()[$name] !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::setCliArguments()
     */
    public function setCliArguments(array $nameValuePairs) : CliTaskInterface
    {
        $this->cliArguments = $nameValuePairs;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliOptions()
     */
    public function getCliOptions() : array
    {
        return $this->cliOptions;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::setCliOptions()
     */
    public function setCliOptions(array $nameValuePairs) : CliTaskInterface
    {
        $this->cliOptions = $nameValuePairs;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::getCliOption()
     */
    public function getCliOption(string $name)
    {
        return $this->getCliOptions()[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\CliTaskInterface::hasCliOption()
     */
    public function hasCliOption(string $name) : bool
    {
        return $this->getCliOptions()[$name] !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\GenericTask::getParameters()
     */
    public function getParameters() : array
    {
        return array_merge($this->getCliArguments(), $this->getCliOptions());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\GenericTask::getParameter()
     */
    public function getParameter($name)
    {
        return $this->getParameters()[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\GenericTask::hasParameter()
     */
    public function hasParameter($name) : bool
    {
        return $this->hasCliArgument($name) || $this->hasCliOption($name);
    }
}