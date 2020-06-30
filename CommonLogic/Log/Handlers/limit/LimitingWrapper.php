<?php
namespace exface\Core\CommonLogic\Log\Handlers\limit;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogHandlerInterface;

/**
 * Abstract log handler wrapper to implement any kind of log file cleanup according to the implementation function
 * "limit".
 *
 * @package exface\Core\CommonLogic\Log\Handlers\rotation
 */
abstract class LimitingWrapper implements LogHandlerInterface
{

    /** @var LogHandlerInterface $handler */
    private $handler;
    
    private $limitPerformed = false;

    /**
     * LimitingWrapper constructor.
     *
     * @param LogHandlerInterface $handler
     *            the "real" log handler
     */
    public function __construct(LogHandlerInterface $handler)
    {
        $this->handler = $handler;
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->callLogger($this->handler, $level, $message, $context, $sender);
        if ($this->limitPerformed === false && $this->getWorkbench()->isStarted()) {
            $this->limitPerformed = true;
            
            // Get the time of the last cleanup. There is no need to perform the check
            // more than once a day as the lifetime of the logs is defined in days.
            $ctxtScope = $this->getWorkbench()->getContext()->getScopeInstallation();
            $last_cleanup = $ctxtScope->getVariable('last_log_cleanup');
            if (! $last_cleanup) {
                // If there was no last cleanup value yet, just set to now and skip the rest
                $ctxtScope->setVariable('last_log_cleanup', date("Y-m-d H:i:s"));
                return;
            }
            
            // If the last cleanup took place less then a day ago, skip the rest.
            if (strtotime($last_cleanup) > (time()-(60*60*24))){
                return;
            }
            
            $this->limit();
            
            $ctxtScope->setVariable('last_log_cleanup', date("Y-m-d H:i:s"));
        }
    }

    /**
     * Create and call the underlying, "real" log handler.
     *
     * @param LogHandlerInterface $handler
     *            the "real" log handler
     * @param
     *            $level
     * @param
     *            $message
     * @param array $context            
     * @param iCanGenerateDebugWidgets|null $sender            
     *
     * @return mixed
     */
    protected abstract function callLogger(LogHandlerInterface $handler, $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);

    /**
     * Log file cleanup.
     */
    protected abstract function limit();
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->handler->getWorkbench();
    }
}