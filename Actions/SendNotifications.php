<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\iSendNotifications;
use exface\Core\CommonLogic\Traits\iSendNotificationsTrait;

class SendNotifications extends AbstractAction implements iSendNotifications
{
    use iSendNotificationsTrait;
    
    private $messageUxons = null;
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $dataSheet = $this->getInputDataSheet($task);
        $count = 0;
        try {
            $communicator = $this->getWorkbench()->getCommunicator();
            foreach ($this->getNotificationEnvelopes($dataSheet) as $envelope)
            {
                $communicator->send($envelope);
                $count++;
            }
        } catch (\Throwable $e) {
            if (($e instanceof CommunicationExceptionInterface) || $envelope === null) {
                $sendingError = $e;
            } else {
                $sendingError = new CommunicationNotSentError($envelope, 'Cannot send notification: ' . $e->getMessage(), null, $e);
            }
            throw $sendingError;
        }
        
        $result = ResultFactory::createDataResult($task, $dataSheet);
        $result->setMessage($count . ' notifications send');
        
        return $result;
        
    }
}