<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Traits\SendMessagesFromDataTrait;
use exface\Core\CommonLogic\UxonObject;

/**
 * Sends a messages through communication channels for every row in the input data.
 * 
 * **NOTE:** If the reciepient is a user, the message will only be sent if this user is authorized to read the 
 * data row. You can change this via `send_only_if_data_authorized`.
 * 
 * @author Andrej Kabachnik
 *
 */
class SendMessage extends AbstractAction
{
    use SendMessagesFromDataTrait;
    
    private $messageUxons = null;
    
    private $sendOnlyIfDataAuthorized = true;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $dataSheet = $this->getInputDataSheet($task);
        $count = 0;
        try {
            $communicator = $this->getWorkbench()->getCommunicator();
            foreach ($this->getMessageEnvelopes(($this->messageUxons ?? new UxonObject()), $dataSheet) as $envelope) {
                foreach ($communicator->send($envelope) as $receipt) {
                    if ($receipt && $receipt->isSent()) {
                        $count++;
                    }
                }
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
        $result->setMessage($count . ' messages sent');
        
        return $result;
    }
    
    /**
     * Array of messages to send - each with a separate message model: channel, recipients, etc.
     * 
     * You can either define a message here explicitly by setting the `channel`, etc., or
     * select a `template` and customize it if needed by overriding certain properties. Note, that
     * when using templates, proper autosuggest is only available if you set the channel explicitly
     * too. 
     *
     * The following placeholders can be used anywhere inside each message configuration: in `text`,
     * `recipients` - anywhere:
     *
     * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
     * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key`
     * from the given app
     * - `[#~data:column_name#]` - will be replaced by the value from `column_name` of the data sheet,
     * for which the notification was triggered - only works with notification that have data sheets present!
     * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
     * This means, static formulas will always work, while data-driven formulas will only work on notifications
     * that have data sheets present!
     * 
     * ## Examples
     * 
     * ### Send message using a template
     * 
     * ```
     *  {
     *      "messages": [
     *          {
     *              "template": "my.App.template_alias"
     *          }
     *      ]
     *  }
     * 
     * ```
     * 
     * ### Send custom message without a template
     * 
     * ```
     *  {
     *      "messages": [
     *          {
     *              "channel": "exface.Core.NOTIFICATION",
     *              "recipient_roles": ["exface.Core.ADMINISTRATOR"],
     *              "title": "New ticket: [#ticket_title#]",
     *              "text": "A new ticket has been created!"
     *          }
     *      ]
     *  }
     * 
     * ```
     *
     * @uxon-property messages
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"": ""}]
     *
     * @param UxonObject $arrayOfMessages
     * @return SendMessage
     */
    public function setMessages(UxonObject $arrayOfMessages) : SendMessage
    {
        $this->messageUxons = $arrayOfMessages;
        return $this;
    }
    
    /**
     * Set to FALSE to send messages for input data even if the recipient user is not authorized to read the corresponding data row
     *
     * By default, the action will check every data row to see if the recipient user
     * is authorized to read it and will only send the message if so.
     *
     * This option only applies if the recipient is a user, a user role, or anything else, 
     * that implies a message being sent ot a user.
     *
     * @uxon-property send_only_if_data_authorized
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $trueOrFalse
     * @return SendMessage
     */
    protected function setSendOnlyIfDataAuthorized(bool $trueOrFalse) : SendMessage
    {
        $this->sendOnlyIfDataAuthorized = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @see SendMessagesFromDataTrait::willSendOnlyForAuthorizedData()
     */
    protected function willSendOnlyForAuthorizedData() : bool
    {
        return $this->sendOnlyIfDataAuthorized;
    }
}