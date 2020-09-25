<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\DateTimeDataType;

class TaskQueue implements TaskQueueInterface
{
    CONST QUEUE_STATUS_QUEUED = 10;
    CONST QUEUE_STATUS_INPROGRESS = 50;
    CONST QUEUE_STATUS_ERROR = 70;
    CONST QUEUE_STATUS_DONE = 99;    
    
    private $exface = null;
    
    /**
     * 
     * @param WorkbenchInterface $exface
     */
    public final function __construct(WorkbenchInterface $exface)
    {
        $this->exface = $exface;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\WorkbenchInterface
     */
    protected function getWorkbench()
    {
        return $this->exface;    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::handle()
     */
    public function handle(TaskInterface $task, string $producer, array $topics, $sync = false): ResultInterface
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.TASK_QUEUE');
        $dataSheet->getColumns()->addFromUidAttribute();
        if ($task->hasParameter('assignedOn')) {
            $assignedOn = $task->getParameter('assignedOn');
        } else {
            $assignedOn = DateTimeDataType::now();
        }
        $dataSheet->addRow([
            'TASK_UXON' => $task->exportUxonObject()->toJson(),
            'STATUS' => self::QUEUE_STATUS_QUEUED,
            'OWNER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
            'PRODUCER' => $producer,
            'TASK_ASSIGNED_ON' => $assignedOn
        ]);
        $dataSheet->dataCreate();
        if ($sync === false) {
            throw new NotImplementedError('Async queue operations not implemented yet!');
        } else {
            $dataSheet->setCellValue('SYNC_FLAG', 0, true);
        }
        try {
            $dataSheet->setCellValue('STATUS', 0, self::QUEUE_STATUS_INPROGRESS);
            $dataSheet->dataUpdate();
            $result = $this->getWorkbench()->handle($task);
            $dataSheet->setCellValue('RESULT', 0, $result->getResponseCode() . ' - ' . $result->getMessage());
            $dataSheet->setCellValue('STATUS', 0, self::QUEUE_STATUS_DONE);
            $dataSheet->dataUpdate();
            return $result;
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            //$this->getWorkbench()->getLogger()->logException($e);
            $dataSheet->setCellValue('STATUS', 0, self::QUEUE_STATUS_ERROR);
            $dataSheet->setCellValue('ERROR_MESSAGE', 0, $e->getMessage());
            $dataSheet->setCellValue('ERROR_LOGID', 0, $e->getAlias());
            $dataSheet->dataUpdate();
            throw $e;
        }
    }   
}