<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Base class for queue prototypes with implementations for UXON configuration and topic matching.
 * 
 * This class has nothing to do with how or even if the queue ist persisted. 
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractTaskQueue implements TaskQueueInterface, WorkbenchDependantInterface
{
    use ImportUxonObjectTrait;
    
    const MATCH_OP_ONE_OF = 'one_of';
    
    const MATCH_OP_ALL_OF = 'all_of';
    
    const MATCH_OP_EXACTLY = 'exactly';
    
    const MATCH_OP_NONE = 'none';
    
    const MATCH_OP_ANY = 'any';
    
    private $workbench = null;
    
    private $name = null;
    
    private $uid = null;
    
    private $alias = null;
    
    private $appSelector = null;
    
    private $configUxon = null;
    
    private $topicsMatcher = null;
    
    private $allowOtherQueuesToHandleSameTasks = false;
    
    private $messageIdsUniquePerProducer = true;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $alias
     * @param AppSelectorInterface|string $appSelector
     * @param string $name
     * @param UxonObject $configUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $uid, string $alias, $appSelector = null, string $name = null, UxonObject $configUxon = null)
    {
        $this->workbench = $workbench;
        $this->uid = $uid;
        $this->alias = $alias;
        $this->appSelector = $appSelector;
        $this->name = $name;
        $this->configUxon = $configUxon;
        
        if ($configUxon !== null) {
            $this->importUxonObject($configUxon);
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::canHandle()
     */
    public function canHandle(TaskInterface $task, array $topics = [], string $producer = null): bool
    {
        return $this->matchTopics($topics);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    public function getName(): string
    {
        return $this->name ?? lcfirst(str_replace('_', ' ', mb_strtolower($this->alias)));
    }

    public function getNamespace()
    {
        return $this->getApp()->getAliasWithNamespace();
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }

    public function exportUxonObject()
    {
        return $this->configUxon;
    }
    
    public function getApp() : ?AppInterface
    {
        return $this->getWorkbench()->getApp($this->appSelector);
    }
    
    public function getUid() : string
    {
        return $this->uid;
    }
    
    /**
     * This queue will handle tasks matching these topics.
     * 
     * If no topics specified, the queue will handle a task with any set of topics
     * 
     * @uxon-property topics
     * @uxon-type object
     * @uxon-template {"all_of": [""], "one_of": [""], "exactly": [""]}
     * 
     * @param UxonObject $uxon
     * @return TaskQueueInterface
     */
    protected function setTopics(UxonObject $uxon) : TaskQueueInterface
    {
        $this->topicsMatcher = $uxon->toArray();
        return $this;
    }
    
    /**
     * 
     * @param array $taskTopics
     * @return bool
     */
    protected function matchTopics(array $taskTopics) : bool
    {
        $result = false;
        
        foreach ($this->topicsMatcher ?? [] as $op => $topics) {
            switch (strtolower($op)) {
                case self::MATCH_OP_ALL_OF: 
                    $result = empty(array_diff($topics, $taskTopics));
                    break;
                case self::MATCH_OP_ONE_OF:
                    $result = ! empty(array_intersect($topics, $taskTopics));
                    break;
                case self::MATCH_OP_EXACTLY:
                    $result = empty(array_diff($topics, $taskTopics)) && empty(array_diff($taskTopics, $topics));
                    break;
                case self::MATCH_OP_NONE:
                    $result = empty($taskTopics);
                    break;
                case self::MATCH_OP_ANY:
                    $result = true;
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Set to `true` to allow multiple queues to handle the same task
     * 
     * @uxon-property allow_other_queues_to_handle_same_tasks
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return TaskQueueInterface
     */
    protected function setAllowOtherQueuesToHandleSameTasks(bool $trueOrFalse) : TaskQueueInterface
    {
        $this->allowOtherQueuesToHandleSameTasks = $trueOrFalse;
        return $this;
    }
    
    public function getAllowOtherQueuesToHandleSameTasks() : bool
    {
        return $this->allowOtherQueuesToHandleSameTasks;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::canHandleAnyTask()
     */
    public function canHandleAnyTask() : bool
    {
        return empty($this->topicsMatcher);
    }
    
    /**
     * 
     * @return bool
     */
    public function getMessageIdsUniquePerProducer() : bool
    {
        return $this->messageIdsUniquePerProducer;
    }
    
    /**
     * Set to FALSE to allow multiple messages with the same id and producer.
     * 
     * By default, a message is only processed if there is no other message from
     * the same producer with the same message id, that was processed previously.
     * 
     * @uxon-property message_ids_unique_per_producer
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return AbstractTaskQueue
     */
    public function setMessageIdsUniquePerProducer(bool $value) : AbstractTaskQueue
    {
        $this->messageIdsUniquePerProducer = $value;
        return $this;
    }
}