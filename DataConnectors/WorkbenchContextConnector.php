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
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;

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
        if ($message instanceof NotificationMessage) {
            $notification = $message;
        } else {
            $notification = NotificationMessage::fromOtherMessageType($message);
        }
        
        $errors = [];
        $userIds = $this->getUserUids($message->getRecipients(), $errors, $message->getRecipientsToExclude());
        
        if (! empty($userIds)) {
            NotificationContext::send($notification, $userIds);
        }
        
        foreach ($errors as $e) {
            $this->getWorkbench()->getLogger()->logException(new CommunicationNotSentError($message, 'Cannot send in-app notification: ' . $e->getMessage(), null, $e));
        }
        
        return new CommunicationReceipt($message, $this);
    }
    
    /**
     *
     * @param array $recipients
     * @return array
     */
    protected function getUserUids(array $recipients, array &$errors, array $excludeRecipients = []) : array
    {
        $userUids = [];
        foreach ($recipients as $recipient) {
            foreach ($excludeRecipients as $excl) {
                if ($excl->is($recipient)) {
                    continue 2;
                }
            }
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $userUids = array_merge($userUids, $this->getUserUids($recipient->getRecipients(), $errors, $excludeRecipients));
                    break;
                case $recipient instanceof UserRecipientInterface:
                    try {
                        if ($recipient->isMuted()) {
                            break;
                        }
                        $userUids[] = $recipient->getUserUid();
                    } catch (UserNotFoundError $e) {
                        $errors[] = $e;
                    }
                    break;
                default:
                    // TODO
            }
        }
        
        return $userUids;
    }
}