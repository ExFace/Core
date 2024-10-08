<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\DataConnectors\Traits\IDoNotSupportTransactionsTrait;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\CommonLogic\Communication\CommunicationReceipt;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Email;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Communication\Messages\EmailMessage;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\DataTypes\EmailDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Communication\Messages\TextMessage;
use exface\Core\DataTypes\HtmlDataType;
use exface\Core\Exceptions\RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use exface\Core\Interfaces\Communication\RecipientInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\DataTypes\RegularExpressionDataType;
use exface\Core\Exceptions\UnexpectedValueException;
use Symfony\Component\Mailer\SentMessage;

/**
 * Sends emails via SMTP
 * 
 * This connector uses the popular [Symfony mailer component](https://symfony.com/doc/current/mailer.html).
 * 
 * This connector is typically used within a communication channel. In the simplest case,
 * you will need to set up an SMTP connection and assign it to the default email channel
 * `exface.Core.EMAIL` - any messages sent through this channel will then be directed to
 * the configured SMTP server. 
 * 
 * You can also set up your own communicatio channel to define a default message structure, etc.
 * 
 * ## Examples
 * 
 * ### SMTP Server without authentication
 * 
 * ```
 *  {
 *      "host": "SMTP.yourdomain.com",
 *      "port": 25,
 *      "tls": true,
 *      "user": "you@yourdomain.com",
 *      "password": "yourSmptPassword"
 *  }
 *  
 * ```
 * 
 * ### SMTP Server with TLS authentication
 * 
 * ```
 *  {
 *      "host": "SMTP.yourdomain.com",
 *      "port": 465,
 *      "tls": true,
 *      "user": "username",
 *      "password": "password"
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class SmtpConnector extends AbstractDataConnector implements CommunicationConnectionInterface
{
    use IDoNotSupportTransactionsTrait;

    private $dsn = null;
    
    private $scheme = null;
    
    private $host = null;
    
    private $port = 25;
    
    private $user = null;
    
    private $password = null;
    
    private $options = [];
    
    private $tls = false;
    
    private $from = null;
    
    private $headers = [];
    
    private $suppressAutoResponse = true;
    
    private $bodyFooter = null;
    
    private $bodyHeader = null;
    
    private $errorIfEmptyTo = false;
    
    private $errorIfEmptyBody = false;
    
    private $ignorePatterns = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        return;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! is_a($query, '\\axenox\\UrlDataxenox\\Notifier\\DataSources\\SymfonyNotifierMessageDataQuery')) {
            throw new DataConnectionQueryTypeError($this, 'Invalid query type for connector "' . $this->getAliasWithNamespace() . '": expecting "SymfonyNotifierMessageDataQuery", received "' . get_class($query) . '"!');
        }
        $this->sendEmail($query->getMessage());
        return $query;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        return;
    }
    
    /**
     * 
     * @return Dsn
     */
    protected function getDsn() : Dsn
    {
        if ($this->dsn !== null) {
            return Dsn::fromString($this->dsn);
        }
        return new Dsn($this->getScheme(), $this->getHost(), $this->getUser(), $this->getPassword(), $this->getPort(), $this->getOptions());
    }
    
    /**
     * Custom DSN as used in Symfony mailer and Swift mailer libraries
     * 
     * @uxon-property dsn
     * @uxon-type uri
     * @uxon-template smtps://smtp.example.com?
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setDsn(string $value) : SmtpConnector
    {
        $this->dsn = $value;
        return $this;
    }
    
    /**
     * 
     * @return TransportInterface
     */
    protected function getTransport() : TransportInterface
    {
        // $transport = new EsmtpTransport($this->getHost(), $this->getPort(), $this->getTls());
        $transport = (new EsmtpTransportFactory())->create($this->getDsn());
        return $transport;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationConnectionInterface::communicate()
     */
    public function communicate(CommunicationMessageInterface $message) : CommunicationReceiptInterface
    {
        if (! ($message instanceof TextMessage)) {
            throw new CommunicationNotSentError($message, 'Cannot send email: invalid message type!');
        }
        
        $email = (new Email());
        
        // Headers
        foreach ($this->getMessageHeaders() as $header => $value) {
            $email->getHeaders()->addTextHeader($header, $value);
        }
        
        // Addresses
        if ($from = $this->getFrom()) {
            $email->from($from);
        }
        $addresses = $this->getEmailAddresses($message->getRecipients(), $message->getRecipientsToExclude());
        // Ignore/error if no to-addresses defined
        if (empty($addresses)) {
            if ($this->isErrorIfEmptyTo()) {
                throw new CommunicationNotSentError($message, 'Failed to send email via "' . $this->getAliasWithNamespace() . '": no to-addresses defined!');
            } else {
                return new CommunicationReceipt($message, $this, null, null, true);
            }
        }
        $email->addTo(...$addresses);
        
        // Email specific stuff
        if ($message instanceof EmailMessage) {
            foreach ($this->getEmailAddresses($message->getRecipientsCC()) as $addr) {
                if (in_array($addr, $addresses)) {
                    continue;
                }
                $addresses[] = $addr;
                $email->addCc($addr);
            }
            foreach ($this->getEmailAddresses($message->getRecipientsBCC()) as $addr) {
                if (in_array($addr, $addresses)) {
                    continue;
                }
                $addresses[] = $addr;
                $email->addCc($addr);
            }
            if (null !== $replyTo = $message->getReplyTo()) {
                $email->replyTo($replyTo->getEmail());
            }
            
            // Priority
            if ($priority = $message->getPriority()) {
                $email->priority($priority);
            }
            
            if ($attachmentPath = $message->getAttachmentPath()) {
                $email->attachFromPath($attachmentPath);
            }
            
            $email->subject($message->getSubject() ?? '');
        }
        
        $body = $message->getText();
        // Ignore/error if body empty
        if ($body === '' || $body === null) {
            if ($this->isErrorIfEmptyBody()) {
                throw new CommunicationNotSentError($message, 'Failed to send email via "' . $this->getAliasWithNamespace() . '": no message body defined!');
            } else {
                return new CommunicationReceipt($message, $this, null, null, true);
            }
        }
        $footer = $this->getFooter();
        $header = $this->getHeader();
        if (HtmlDataType::isValueHtml($body)) {
            $footer = ($footer !== null ? '<footer>' . $footer . '</footer>': '');
            $footer = ($header !== null ? '<header>' . $header . '</header>': '');
            $email->html($header . $body . $footer);
        } else {
            $footer = ($footer !== null ? "\n\n" . $footer : '');
            $header = ($footer !== null ? "\n\n" . $header : '');
            $email->text($header . $body . $footer);
        }
        
        try {
            $sentMessage = $this->sendEmail($email);
        } catch (\Throwable $e) {
            $debug = $this->buildMarkdownDebug($email, $sentMessage, $e);
            throw new CommunicationNotSentError($message, 'Failed to send email via "' . $this->getAliasWithNamespace() . '": ' . $e->getMessage(), null, $e, $this, $debug);
        }
        
        $debugCallback = function(DebugMessage $debugWidget) use ($email, $sentMessage) {
            $debug = $this->buildMarkdownDebug($email, $sentMessage);
            $debugWidget->addTab(WidgetFactory::createFromUxonInParent($debugWidget, new UxonObject([
                'widget_type' => 'Tab',
                'caption' => 'SMTP debug',
                'widgets' => [
                    [
                        'widget_type' => 'Markdown',
                        'width' => '100%',
                        'height' => '100%',
                        'value' => $debug
                    ]
                ]
            ])));
            return $debugWidget;
        };
        
        return new CommunicationReceipt($message, $this, $debugCallback);
    }
    
    /**
     * 
     * @param Email $email
     * @return SentMessage
     */
    protected function sendEmail(Email $email) : SentMessage
    {
        return $this->getTransport()->send($email);
    }
    
    /**
     * 
     * @param Email $email
     * @param SentMessage $sentMessage
     * @param \Throwable $e
     * @return string
     */
    protected function buildMarkdownDebug(Email $email, SentMessage $sentMessage = null, \Throwable $e = null) : string
    {
        $debug = <<<MD
        
## Email message

```
{$email->toString()}
```
MD;
        if ($e instanceof TransportExceptionInterface) {
            $debug .= <<<MD
    
## SMTP log

```
{$e->getDebug()}
```
MD;
        }
        if ($sentMessage !== null) {
            $debug .= <<<MD
    
## Message debug output

```
{$sentMessage->getDebug()}
```
MD;
        }
        return $debug;
    }
    
    /**
     * 
     * @param RecipientInterface[] $recipients
     * @return string[]
     */
    protected function getEmailAddresses(array $recipients, array $recipientsToExclude = []) : array
    {
        $addrs = [];
        foreach ($recipients as $recipient) {
            foreach ($recipientsToExclude as $excl) {
                if ($excl->is($recipient)) {
                    continue 2;
                }
            }
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $addrs = array_merge($addrs, $this->getEmailAddresses($recipient->getRecipients(), $recipientsToExclude));
                    break;
                case $recipient instanceof EmailRecipientInterface:
                    if (($recipient instanceof UserRecipientInterface) && $recipient->isMuted()) {
                        break;
                    }
                    if ($email = $recipient->getEmail()) {
                        foreach (explode(';', $email) as $addr) {
                            $addrs[] = trim($addr);
                        }
                    }
                    break;
                default:
                    $this->getWorkbench()->getLogger()->logException(new RuntimeException('Failed to send email to recipient "' . $recipient->__toString() . '": cannot determin email address!'));
                    break;
            }
        }
        
        $addrs = array_unique($addrs);
        
        foreach ($this->getIgnoreAddressPatterns() as $pattern) {
            $addrs = array_filter($addrs, function(string $email) use ($pattern) {
                return preg_match($pattern, $email) !== 1;
            });
        }
        
        return $addrs;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getIgnoreAddressPatterns() : array
    {
        return $this->ignorePatterns;
    }
    
    /**
     * Email address patterns to ignore: e.g. `/.*@mydomain\.com/i`
     * 
     * Recipients of any kind will be ignored if their email address matches
     * any of the provided regular expressions.
     * 
     * @uxon-property ignore_address_patterns
     * @uxon-type array
     * @uxon-template ["/.*@mydomain\\.com/i"]
     * 
     * @param string|UxonObject $value
     * @throws UnexpectedValueException
     * @return SmtpConnector
     */
    public function setIgnoreAddressPatterns($value) : SmtpConnector
    {
        switch (true) {
            case $value instanceof UxonObject:
                $array = $value->toArray();
                break;
            case is_array($value):
                $array = $value;
                break;
            default: throw new UnexpectedValueException('Invalid value "' . $value . '" for ignore_address_patterns for SMTP connector: must be an array of strings or regular expressions!');
        }
        foreach ($array as $pattern) {
            if (! RegularExpressionDataType::isRegex($pattern)) {
                throw new UnexpectedValueException('Invalid value "' . $pattern . '" for ignore_address_patterns for SMTP connector: each pattern must be a valid regular expression like `/.*@mydomain\.com/i`!');
            }
        }
        $this->ignorePatterns = $array;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getScheme() : string
    {
        return $this->scheme ?? ($this->getTls() ? 'smpts://' : 'smpt://');
    }
    
    /**
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setScheme(string $value) : SmtpConnector
    {
        $this->scheme = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getHost() : string
    {
        return $this->host;
    }

    
   /**
    * The host name or IP address of the SMTP server
    * 
    * @uxon-property host
    * @uxon-type uri
    * 
    * @param string $value
    * @return SmtpConnector
    */
    protected function setHost(string $value) : SmtpConnector
    {
        $this->host = $value;
        return $this;
    }
   
    /**
     * 
     * @return int
     */
    protected function getPort() : int
    {
        return $this->port;
    }
    
    /**
     * SMTP server port - typically 25, 465 or 587
     *
     * @uxon-property port
     * @uxon-type uri
     * @uxon-default 25
     *
     * @return string
     */
    protected function setPort(int $value) : SmtpConnector
    {
        $this->port = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getUser() : ?string
    {
        return $this->user;
    }
    
    /**
     * Username for SMTP server authentication
     * 
     * @uxon-property user
     * @uxon-type string
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setUser(string $value) : SmtpConnector
    {
        $this->user = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getPassword() : ?string
    {
        return $this->password;
    }
    
    /**
     * Password for SMTP server authentication
     * 
     * @uxon-property password
     * @uxon-type password
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setPassword(string $value) : SmtpConnector
    {
        $this->password = $value;
        return $this;
    }
    
    protected function getTls() : bool
    {
        return $this->tls;
    }
    
    /**
     * Set to TRUE to use TLS for secure SMTP
     *
     * @uxon-property tls
     * @uxon-type boolean
     * @uxon-default false
     *
     * @return string
     */
    protected function setTls(bool $value) : SmtpConnector
    {
        $this->tls = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getOptions() : array
    {
        return $this->options;
    }
    
    /**
     *
     * @return string
     */
    protected function getFrom() : ?string
    {
        return $this->from;
    }
    
    /**
     * From email address
     *
     * @uxon-property from
     * @uxon-type string
     * @uxon-required true
     *
     * @param string $value
     * @return EmailMessage
     */
    protected function setFrom(string $value) : SmtpConnector
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
    protected function getMessageHeaders() : array
    {
        $headers = $this->headers;
        if ($this->getSuppressAutoResponse() === true) {
            $headers['X-Auto-Response-Suppress'] = 'OOF, DR, RN, NRN, AutoReply';
        }
        return $headers;
    }
    
    /**
     * Custom message headers for all messages
     *
     * @uxon-property message_headers
     * @uxon-type object
     * @uxon-template {"X-Auto-Response-Suppress": "OOF, DR, RN, NRN, AutoReply"}
     *
     * @param string[]|UxonObject $value
     * @return EmailMessage
     */
    protected function setMessageHeaders($value) : SmtpConnector
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
     * 
     * @return Bool
     */
    protected function getSuppressAutoResponse() : Bool
    {
        return $this->suppressAutoResponse;
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
    protected function setSuppressAutoResponse(bool $value) : SmtpConnector
    {
        $this->suppressAutoResponse = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getFooter() : ?string
    {
        return $this->bodyFooter;
    }
    
    /**
     * A footer to be appended to all messages sent through this connection (plain text or HTML)
     * 
     * @uxon-property footer
     * @uxon-type string
     * 
     * @param string $value
     * @return SmtpConnector
     */
    protected function setFooter(string $value) : SmtpConnector
    {
        $this->bodyFooter = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getHeader() : ?string
    {
        return $this->bodyHeader;
    }
    
    /**
     * A header to be placed on top of all messages sent through this connection (plain text or HTML)
     *
     * @uxon-property header
     * @uxon-type string
     *
     * @param string $value
     * @return SmtpConnector
     */
    protected function setHeader(string $value) : SmtpConnector
    {
        $this->bodyHeader = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isErrorIfEmptyTo() : bool
    {
        return $this->errorIfEmptyTo;
    }
    
    /**
     * Set to TRUE to raise an error if a message has an empty to-field instead of silently ignoring such messages
     * 
     * @uxon-property error_if_empty_to
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return SmtpConnector
     */
    protected function setErrorIfEmptyTo(bool $value) : SmtpConnector
    {
        $this->errorIfEmptyTo = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isErrorIfEmptyBody() : bool
    {
        return $this->errorIfEmptyBody;
    }
    
    /**
     * Set to TRUE to raise an error if a message has an empty body instead of silently ignoring such messages
     * 
     * @uxon-property error_if_empty_body
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return SmtpConnector
     */
    protected function setErrorIfEmptyBody(bool $value) : SmtpConnector
    {
        $this->errorIfEmptyBody = $value;
        return $this;
    }
}