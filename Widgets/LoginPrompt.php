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
        while (! ($authErr instanceof AuthenticationFailedError)) {
            $authErr = $authErr->getPrevious();
        }
        if ($authErr !== null) {
            $workbench = $page->getWorkbench();
            $workbench->getLogger()->logException($exception);
            /* @var $loginPrompt \exface\Core\Widgets\LoginPrompt */
            $loginPrompt = WidgetFactory::create($page, 'LoginPrompt');
            $loginPrompt->setObjectAlias('exface.Core.LOGIN_DATA');
            $loginFormCreated = false;
            
            $provider = $authErr->getAuthenticationProvider();
            if ($provider instanceof DataConnectionInterface) {
                // Saving connection credentials is only possible if a user is authenticated!
                if ($workbench->getSecurity()->getAuthenticatedToken()->isAnonymous() === false) {
                    $loginPrompt = $provider->createLoginWidget($loginPrompt, true, new UserSelector($workbench, $workbench->getSecurity()->getAuthenticatedToken()->getUsername()));
                    $loginPrompt->setCaption($workbench->getCoreApp()->getTranslator()->translate('SECURITY.CONNECTIONS.LOGIN_TITLE'));
                    $loginPrompt->getMessageList()->addError($authErr->getMessage());
                    $loginFormCreated = true;
                }
            } else {
                $loginPrompt = $provider->createLoginWidget($loginPrompt);
                $loginPrompt->getMessageList()->addError($authErr->getMessage());
                $loginFormCreated = true;
            }
        }
        
        if ($loginFormCreated === false) {
            throw new RuntimeException('Cannot create login page from error!');
        }
        
        return $loginPrompt;
    }
}