<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use Symfony\Component\Console\Exception\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\DataTypes\QueuedTaskStateDataType;

/**
 * Performs the task immediately after inserting in the queue in the same transaction.
 * 
 * @author Andrej Kabachnik
 *
 */
class SyncTaskQueue extends AsyncTaskQueue
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Queue\AsyncTaskQueue::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null) : ResultInterface
    {
        $dataSheet = $this->createQueueDataSheet($task, $topics, $producer, $messageId);
        $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_INPROGRESS);
        $dataSheet->dataCreate();
        
        try {
            $result = $this->getWorkbench()->handle($task);
            $dataSheet->setCellValue('RESULT', 0, $result->getResponseCode() . ' - ' . $result->getMessage());
            $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_DONE);
            $dataSheet->dataUpdate();
            return $result;
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            //$this->getWorkbench()->getLogger()->logException($e);
            $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_ERROR);
            $dataSheet->setCellValue('ERROR_MESSAGE', 0, $e->getMessage());
            $dataSheet->setCellValue('ERROR_LOGID', 0, $e->getAlias());
            $dataSheet->dataUpdate();
            throw $e;
        }
    }
}