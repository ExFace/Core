<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\CommonLogic\Communication\CommunicationReceipt;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Email;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Communication\Messages\EmailMessage;
use exface\Core\Exceptions\Communication\CommunicationNotSentError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class SmtpConnector extends AbstractDataConnectorWithoutTransactions implements CommunicationConnectionInterface
{
    private $dsn = null;
    
    private $scheme = null;
    
    private $host = null;
    
    private $port = 25;
    
    private $user = null;
    
    private $password = null;
    
    private $options = [];
    
    private $tls = false;
    
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
        if (! $query instanceof SymfonyNotifierMessageDataQuery) {
            throw new DataConnectionQueryTypeError($this, 'Invalid query type for connector "' . $this->getAliasWithNamespace() . '": expecting "SymfonyNotifierMessageDataQuery", received "' . get_class($query) . '"!');
        }
        $this->getTransport()->send($query->getMessage());
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
    
    protected function getDsn() : Dsn
    {
        return new Dsn($this->getScheme(), $this->getHost(), $this->getUser(), $this->getPassword(), $this->getPort(), $this->getOptions());
    }
    
    protected function getTransport() : TransportInterface
    {
        // $transport = new EsmtpTransport($this->getHost(), $this->getPort(), $this->getTls());
        $transport = (new EsmtpTransportFactory())->create($this->getDsn());
        return $transport;
    }
    
    protected function getMailer() : MailerInterface
    {
        $mailer = new Mailer($this->getTransport());
        return $mailer;
    }
    
    public function communicate(CommunicationMessageInterface $message) : CommunicationReceiptInterface
    {
        if (! ($message instanceof EmailMessage)) {
            throw new CommunicationNotSentError($message, 'Cannot send email: invalid message type!');
        }
        $mailer = $this->getMailer();
        
        $email = (new Email());
        
        $fromAddr = $message->getFrom();
        
        $email->from($fromAddr);
        foreach ($this->getEmails($message->getRecipients()) as $address) {
            $email->addTo($address);
        }
        //->cc('cc@example.com')
        //->bcc('bcc@example.com')
        //->replyTo('fabien@example.com')
        //->priority(Email::PRIORITY_HIGH)
        
        $email->subject($message->getSubject() ?? '');
        if ($message->isHtml()) {
            $email->html($message->getHtml());
        } else {
            $email->text($message->getText());
        }
        
        $mailer->send($email);
        
        return new CommunicationReceipt($message, $this);
    }
    
    protected function getEmails(array $recipients) : array
    {
        $emails = [];
        foreach ($recipients as $recipient) {
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $emails = array_merge($emails, $this->getEmails($recipient->getRecipients()));
                    break;
                case $recipient instanceof EmailRecipientInterface:
                    if ($email = $recipient->getEmail()) {
                        $emails[] = $email;
                    }
                    break;
                default:
                    // TODO
            }
        }
        return $emails;
    }
    
    protected function getScheme() : string
    {
        return $this->scheme ?? ($this->getTls() ? 'smpts://' : 'smpt://');
    }
    
    protected function setScheme(string $value) : SmtpConnector
    {
        $this->scheme = $value;
        return $this;
    }
    
    /**
     * SMTP server host
     * 
     * @uxon-property host
     * @uxon-type uri
     * @uxon-required true
     * 
     * @return string
     */
    protected function getHost() : string
    {
        return $this->host;
    }
    
    protected function setHost(string $value) : SmtpConnector
    {
        $this->host = $value;
        return $this;
    }
    
    protected function getPort() : int
    {
        return $this->port;
    }
    
    /**
     * SMTP server port
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
    
    protected function getUser() : ?string
    {
        return $this->user;
    }
    
    protected function setUser(string $value) : SmtpConnector
    {
        $this->user = $value;
        return $this;
    }
    
    protected function getPassword() : ?string
    {
        return $this->password;
    }
    
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
    
    protected function getOptions() : array
    {
        return $this->options;
    }
    
    protected function setOptions(UxonObject $value) : SmtpConnector
    {
        $this->options = $value;
        return $this;
    }
}