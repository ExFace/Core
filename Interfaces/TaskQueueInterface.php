<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskQueueInterface extends TaskHandlerInterface, AliasInterface, iCanBeConvertedToUxon
{
    /**
     * Puts the task in the queue using it's internal handling logic.
     * 
     * @param TaskInterface $task
     * @param string[] $topics
     * @param string $producer
     * @return ResultInterface
     * 
     * @see \exface\Core\Interfaces\TaskHandlerInterface::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null) : ResultInterface;
    
    /**
     * 
     * @param TaskInterface $task
     * @param array $topics
     * @param string $provider
     * @return bool
     */
    public function canHandle(TaskInterface $task, array $topics = [], string $provider = null) : bool;
    
    /**
     * 
     * @return bool
     */
    public function canHandleAnyTask() : bool;
    
    /**
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @return AppInterface|NULL
     */
    public function getApp() : ?AppInterface;
    
    /**
     * 
     * @return bool
     */
    public function getAllowOtherQueuesToHandleSameTasks() : bool;
}