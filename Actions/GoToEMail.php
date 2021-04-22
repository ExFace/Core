<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\Model\Expression;

/**
 * Opens a new email in your local mail app optionally filling placeholders with input data.
 * 
 * Via the `to` property the recipient of the email is defined.
 * It is possible to also prefill the subject and the body of the email using the `subject` and 
 * `body` properties. All of these properties support placeholders, the `to` property also supports
 * static formulas.
 * 
 * The URL defined in the `url` parameter can contain placeholders, that will be replaced 
 * by values from input data columns with the same names: e.g. the placeholder `[#PATH#]`,
 * will be replaced by the value of the `PATH` column in the first row of the action's 
 * input data. Placeholder values are url-encoded automatically unless you set
 * `urlencode_placeholders` to `false` - e.g. if the entire URL comes from the placeholder.
 * 
 * ## Examples
 * 
 * Open an email with a given mail adress
 * 
 * ```
 * {
 *  "alias": "exface.Core.GoToEMail",
 *  "to": support@powerui.de
 * }
 * 
 * ```
 * 
 * Open an email with prefill of subject and body from input data.
 * 
 * ```
 * {
 *  "alias": "exface.Core.GoToEMail",
 *  "to": "=GetConfig('DEBUG.SUPPORT_EMAIL_ADDRESS')",
 *  "subject": "[#LOG_ID#]",
 *  "body": "[#MESSAGE#]"
 * }
 * 
 * ```
 *
 * @author Ralf Mulansky
 *        
 */
class GoToEMail extends GoToUrl
{
    private $to = null;
    
    private $subject = null;
    
    private $body = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::MAIL_FORWARD);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowUrl::getUrl()
     */
    public function getUrl()
    {
        if ($this->url) {
            return $this->url;
        }
        
        $to = $this->getTo();
       /*if ($to === null || $to === '') {
            throw new ActionInputMissingError($this, 'No recipient or placeholder given to send the mail to.');
        }*/
        
        $url = "mailto: " . $to;
        if ($this->getSubject()) {
            $url .= '?subject=' . $this->getSubject();
        }
        if ($this->getBody()) {
            if ($this->getSubject())  {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $url .= 'body=' . $this->getBody();
        }
        return $url;
    }
    
    /**
     * Defines the subject of the email.
     *
     * @uxon-property subject
     * @uxon-type string
     */
    public function setSubject(string $value) : GoToEMail
    {
        $this->subject = $value;
        return $this;
    }
    
    /**
     * Defines the recipient of the email.
     *
     * @uxon-property to
     * @uxon-type string
     */
    public function setTo(string $value) : GoToEMail
    {
        $this->to = $value;
        return $this;
    }
    
    /**
     * Returns the recipient of the email to send.
     *
     * @return string|NULL
     */
    public function getTo() : ?string
    {
        if (Expression::detectFormula($this->to)) {
            $toExp = ExpressionFactory::createFromString($this->getWorkbench(), $this->to);
            return $toExp->evaluate();
        }
        return $this->to;
    }
    
    /**
     * Returns the subject of the email to send.
     * 
     * @return string|NULL
     */
    public function getSubject() : ?string
    {
        return $this->subject;
    }
    
    /**
     * Defines the body of the email.
     *
     * @uxon-property body
     * @uxon-type string
     */
    public function setBody($value) : GoToEMail
    {
        $this->body = $value;
        return $this;
    }
    
    /**
     * Returns the body of the email.
     * 
     * @return string|NULL
     */
    public function getBody() : ?string
    {
        return $this->body;
    }
    
    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool
    {
        return true;
    }
}
?>