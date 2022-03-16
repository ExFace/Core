<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\EmailDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\EmailPriorityDataType;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\HtmlDataType;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;

/**
 * Email message to be sent to one or multiple email addresses, users or user groups
 * 
 * The to-addresses can be specified explicitly via `recipients` or by naming
 * `recipient_users` and `recipient_roles` to get the email addresses from.
 * 
 * The email consists of the body `text` (either plain text or HTML) and an optional `subject`. 
 * Additionally you can set a `priority`.
 * 
 * @author Andrej Kabachnik
 *
 */
class EmailMessage extends TextMessage
{    
    private $subject = null;
    
    private $priority = null;
    
    /**
     * 
     * @return string|NULL
     */
    public function getSubject() : ?string
    {
        return $this->subject;
    }
    
    /**
     * Email subject
     * 
     * @uxon-property subject
     * @uxon-type string
     * 
     * @param string $value
     * @return EmailMessage
     */
    public function setSubject(string $value) : EmailMessage
    {
        $this->subject = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isHtml() : bool
    {
        return HtmlDataType::isValueHtml($this->getText());
    }
    
    /**
     * The body of the message - either plain text or HTML
     *
     * @uxon-property text
     * @uxon-type string
     *
     * {@inheritDoc}
     * @see \exface\Core\Communication\Messages\TextMessage::setText()
     */
    public function setText(string $value) : CommunicationMessageInterface
    {
        $this->text = $value;
        return $this;
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getPriority() : ?int
    {
        return $this->priority;
    }
    
    /**
     * Message priority: highest, high, normal, low, lowest.
     * 
     * @uxon-property priority
     * @uxon-type [highest,high,normal,low,lowest]
     * @uxon-default normal
     * 
     * @param string|int $value
     * @return EmailMessage
     */
    public function setPriority($value) : EmailMessage
    {
        $this->priority = EmailPriorityDataType::cast($value);
        return $this;
    }
}