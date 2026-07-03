<?php
namespace exface\Core\Interfaces\Tasks;

/**
 * Common interface for tasks, that can have a timeout setting
 * 
 * @author Andrej Kabachnik
 *
 */
interface TimeoutingTaskInterface extends TaskInterface
{
    /**
     * @return \DateInterval|null
     */
    public function getTimeoutInterval() : ?\DateInterval;
}