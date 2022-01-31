<?php
namespace exface\Core\DataConnectors;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\UrlDataConnector\Psr7DataQuery;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Contexts\NotificationContext;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\CommonLogic\Communication\CommunicationReceipt;
use exface\Core\Communication\Messages\NotificationMessage;
use exface\Core\CommonLogic\UxonObject;

/**
 * Reads and writes context of the workbench
 * 
 * @author Andrej Kabachnik
 *        
 */
class WorkbenchContextConnector extends TransparentConnector implements CommunicationConnectionInterface
{

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     * 
     * @return DataQueryInterface
     */
    protected function performQuery(DataQueryInterface $query)
    {
        throw new NotImplementedError('Querying through the WorkbenchContextConnector not implemented yet!');
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        if ($this->getWorkbench()->isStarted() === false) {
            $this->getWorkbench()->start();
        }
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationConnectionInterface::communicate()
     */
    public function communicate(CommunicationMessageInterface $message): CommunicationReceiptInterface
    {
        $context = $this->getWorkbench()->getContext()->getScopeUser()->getContext(NotificationContext::class);
        
        if ($message instanceof NotificationMessage) {
            $notification = $message;
        } else {
            $notification = NotificationMessage::fromOtherMessageType($message);
        }
        
        $context->send($notification, $this->getUserUids($message->getRecipients()));
        
        return new CommunicationReceipt($message, $this);
    }
    
    /**
     *
     * @param array $recipients
     * @return array
     */
    protected function getUserUids(array $recipients) : array
    {
        $userUids = [];
        foreach ($recipients as $recipient) {
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $userUids = array_merge($userUids, $this->getUserUids($recipient->getRecipients()));
                    break;
                case $recipient instanceof UserRecipientInterface:
                    $userUids[] = $recipient->getUserUid();
                    break;
                default:
                    // TODO
            }
        }
        return $userUids;
    }
}