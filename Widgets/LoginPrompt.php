<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iShowMessageList;
use exface\Core\Widgets\Traits\iShowMessageListTrait;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Security\AuthenticationIncompleteError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * A login promt with potentially multiple forms for different authentication options (i.e. local login, LDAP, OAuth, etc.).
 * 
 * @author Andrej Kabachnik
 *        
 */
class LoginPrompt extends Container implements iFillEntireContainer, iShowMessageList
{
    use iShowMessageListTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowMessageList::getMessageList()
     */
    public function getMessageList() : MessageList
    {
        if ($this->messageList === null) {
            $this->messageList = WidgetFactory::create($this->getPage(), 'MessageList', $this);
            if ($this->getWorkbench()->getConfig()->hasOption("LOGIN.PROMPT.MESSAGES")) {
                $uxon = $this->getWorkbench()->getConfig()->getOption("LOGIN.PROMPT.MESSAGES");
                if ($uxon) {
                    $this->setMessages($uxon);
                }
            }
            
        }
        return $this->messageList;
    }
    
    /**
     * Returns the panels of the Split.
     * Technically it is an alias for Split::getWidgets() for better readability.
     *
     * @see getWidgets()
     */
    public function getForms()
    {
        return $this->getWidgets();
    }
    
    public function getWidgets(callable $filter = null)
    {
        if (parent::hasWidgets() === false) {
            $this->getWorkbench()->getSecurity()->createLoginWidget($this);
        }
        return parent::getWidgets($filter);
    }

    
    public function addForm(Form $widget, int $position = null) : LoginPrompt
    {
        return $this->addWidget($widget, $position);
    }

    /**
     * Array of login forms (im multiple login options required).
     * 
     * @uxon-property forms
     * @uxon-type \exface\Core\Widgets\LoginPrompt[]
     * @uxon-template [{"caption": "", "widgets": [{"": ""}]}]
     *
     * @param UxonObject|LoginPrompt|AbstractWidget $widget_or_uxon_array
     * @return \exface\Core\Widgets\LoginPrompt
     */
    public function setForms($widget_or_uxon_array) : LoginPrompt
    {
        return $this->setWidgets($widget_or_uxon_array);
    }

    /**
     * @deprecated use setForms() instead!
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                $widget = WidgetFactory::createFromUxonInParent($this, $w, 'Form');
            } elseif ($w instanceof WidgetInterface) {
                // If it is already a widget, take it for further checks
                $widget = $w;
            } else {
                throw new WidgetConfigurationError($this, 'Invalid element "' . $w  . '" in property "forms" of widget "' . $this->getWidgetType() . '": expecting UXON widget description or instantiated widget object!');
            }
            
            if (! ($widget instanceof Form)) {
                throw new WidgetConfigurationError($this, 'Cannot use widget "' . $widget->getWidgetType()  . '" within property "forms" of widget "' . $this->getWidgetType() . '": only Form widgets or derivatives allowed!');
            } else {
                $widgets[] = $widget;
            }
        }
        
        return parent::setWidgets($widgets);
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return $this->getWidgetFirst();
    }

    /**
     * Creates a LoginPrompt for a given unauthorized-exception (even if wrapped in another error).
     * 
     * @param UiPageInterface $page
     * @param \Throwable $exception
     * @throws RuntimeException
     * @return LoginPrompt
     */
    public static function createFromException(UiPageInterface $page, \Throwable $exception) : LoginPrompt
    {
        $authErr = $exception;
        while (! ($authErr instanceof AuthenticationFailedError) && ($authErr instanceof \Exception)) {
            $authErr = $authErr->getPrevious();
        }
        if ($authErr !== null) {
            $workbench = $page->getWorkbench();
            $workbench->getLogger()->logException($exception);
            /* @var $loginPrompt \exface\Core\Widgets\LoginPrompt */
            $uxon = new UxonObject([
                'widget_type' => 'LoginPrompt',
                'object_alias' => 'exface.Core.LOGIN_DATA'
            ]);
            $loginPrompt = WidgetFactory::createFromUxon($page, $uxon);
            // Make sure, the widget has a different id if it is created for an AuthenticationIncompleteError
            // because it will normally be displayed "on-top" of the regular login promt on the same page.
            // This is a bit of a hack, overwriting the autogenerated id, but it helps because that id is
            // used in the id paths of child elements. At the time, when this widget is create, the previous
            // LoginForm is not there anymore in the server, but it may be still present on the client. Thus,
            // the client will have id-collisions, but the server will not.
            if ($authErr instanceof AuthenticationIncompleteError) {
                $loginPrompt->setIdAutogenerated($loginPrompt->getIdAutogenerated() . md5($authErr->getMessage()));
            }
            $loginFormCreated = false;
            
            $provider = $authErr->getAuthenticationProvider();
            if ($provider instanceof DataConnectionInterface) {
                // Saving connection credentials is only possible if a user is authenticated!
                if ($workbench->getSecurity()->getAuthenticatedToken()->isAnonymous() === false) {
                    $loginPrompt = $provider->createLoginWidget($loginPrompt, true, new UserSelector($workbench, $workbench->getSecurity()->getAuthenticatedToken()->getUsername()));
                    $loginPrompt->setCaption($workbench->getCoreApp()->getTranslator()->translate('SECURITY.CONNECTIONS.LOGIN_TITLE'));
                    $loginFormCreated = true;
                }
            } else {
                $loginPrompt = $provider->createLoginWidget($loginPrompt);
                //populate login form with standard login prompts
                $loginPrompt->getMessageList();
                $loginFormCreated = true;
            }
            
            // If the exception is debug-level only (e.g. access denied for anonymous users), don't add 
            // a message - this is nothing a user should sse.
            if ($loginFormCreated && strcasecmp($authErr->getLogLevel(), LoggerInterface::DEBUG) !== 0) {
                $loginPrompt->getMessageList()->addMessageFromModel($authErr->getMessageModel($page->getWorkbench()));
            }
        }
        
        if ($loginFormCreated === false) {
            throw new RuntimeException('Cannot create login page from error!');
        }
        
        return $loginPrompt;
    }
}