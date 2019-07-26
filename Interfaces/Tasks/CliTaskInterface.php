<?php
namespace exface\Core\Interfaces\Tasks;

/**
 * Interface for task based on command line commands.
 * 
 * Commands generally have arguments and options separately, rather than a single list of
 * parameters. This interface allows tasks to keep arguments and options separately in
 * case an action will depend on this, while still offerent the generic getParameters()
 * method for actions that are unaware of CLI commands.
 * 
 * @author Andrej Kabachnik
 *
 */
interface CliTaskInterface extends TaskInterface
{    
    /**
     *
     * @return array
     */
    public function getCliArguments() : array;
    
    /**
     * 
     * @param string $name
     * @return mixed|NULL
     */
    public function getCliArgument(string $name);
    
    /**
     * 
     * @param string $name
     * @return bool
     */
    public function hasCliArgument(string $name) : bool;
    
    /**
     *
     * @param array $nameValuePairs
     * @return CliTaskInterface
     */
    public function setCliArguments(array $nameValuePairs) : CliTaskInterface;
    
    /**
     *
     * @return array
     */
    public function getCliOptions() : array;
    
    /**
     *
     * @param array $nameValuePairs
     * @return CliTaskInterface
     */
    public function setCliOptions(array $nameValuePairs) : CliTaskInterface;
    
    /**
     * 
     * @param string $name
     * @return mixed|NULL
     */
    public function getCliOption(string $name);
    
    /**
     * 
     * @param string $name
     * @return bool
     */
    public function hasCliOption(string $name) : bool;
}