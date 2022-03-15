<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\EmailDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * 
 * @author andrej.kabachnik
 *
 */
class EmailMessage extends TextMessage
{
    private $html = null;
    
    private $subject = null;
    
    private $from = null;
    
    private $headers = [
        'X-Auto-Response-Suppress' => 'OOF, DR, RN, NRN, AutoReply'
    ];
    
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
     * @return string
     */
    public function getHtml() : ?string
    {
        return $this->html;
    }
    
    public function isHtml() : bool
    {
        return $this->html !== null;
    }
    
    /**
     * 
     * @param string $value
     * @return EmailMessage
     */
    public function setHtml(string $value) : EmailMessage
    {
        $this->html = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getFrom() : string
    {
        return $this->from;
    }
    
    /**
     * From email address
     * 
     * @uxon-property from
     * @uxon-type string
     * 
     * @param string $value
     * @return EmailMessage
     */
    public function setFrom(string $value) : EmailMessage
    {
        try {
            $email = EmailDataType::cast($value);
        } catch (DataTypeCastingError $e) {
            throw new InvalidArgumentException('Invalid from-address for email message: "' . $email . '"!');
        }
        $this->from = $email;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getMessageHeaders() : array
    {
        return $this->headers;
    }
    
    /**
     * Custom message headers
     * 
     * @uxon-property message_headers
     * @uxon-type object
     * @uxon-template {"X-Auto-Response-Suppress": "OOF, DR, RN, NRN, AutoReply"}
     * 
     * @param string[]|UxonObject $value
     * @return EmailMessage
     */
    public function setMessageHeaders($value) : EmailMessage
    {
        if ($value instanceof UxonObject) {
            $array  = $value->toArray();
        } elseif (is_array($value)) {
            $array = $value;
        } else {
            throw new InvalidArgumentException('Invalid email message headers: "' . $value . '" - expecting array or UXON!');
        }
        $this->headers = $array;
        return $this;
    }
    
    /**
     * Set to TRUE to tell auto-repliers ("email holiday mode") to not reply to this message because it's an automated email
     * 
     * @uxon-property suppress_auto_response
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return EmailMessage
     */
    public function setSuppressAutoResponse(bool $value) : EmailMessage
    {
        if ($value === true) {
            $this->headers['X-Auto-Response-Suppress'] = 'OOF, DR, RN, NRN, AutoReply';
        } else {
            unset ($this->headers['X-Auto-Response-Suppress']);
        }
        return $this;
    }
}