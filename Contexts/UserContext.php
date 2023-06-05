<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Factories\DataSheetFactory;

/**
 * The UserContext shows the logged in User and some user-related controls like a logout button.
 * 
 * The `UserContext` can only be used within the user context scope.
 *
 * @author Ralf Mulansky
 *        
 */
class UserContext extends AbstractContext
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon()
    {
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        if ($user->isAnonymous() === true) {
            return Icons::USER_SECRET;
        }
        return Icons::USER_CIRCLE_O;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getVisibility()
     */
    public function getVisibility()
    {
        return ContextInterface::CONTEXT_BAR_SHOW_ALLWAYS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {       
        $token = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        $uxon = null;
        $coreApp = $this->getWorkbench()->getCoreApp();
        
        //when user is logged in, build context with user details and logout button
        if ($token->isAnonymous() === false){
            $uxon = [
              "widget_type" => "Form",
              "height" => "100%",
              "object_alias" => "exface.Core.USER",
              "columns_in_grid" => "1",
              "read_only" => true,
              "widgets" => [
                  [
                      "widget_type" => "Message",
                      "value" => $coreApp->getTranslator()->translate('CONTEXT.USER.LOGGED_IN_HINT'),
                      "width" => "100%"
                  ],
                  [
                      "widget_type" => "InputHidden",
                      "attribute_alias" => "UID"
                  ],
                  [
                      "attribute_alias" => "USERNAME",
                      "width" => "100%"
                  ],
                  [
                      "attribute_alias" => "FIRST_NAME",
                      "width" => "100%"
                  ],
                  [
                      "attribute_alias" => "LAST_NAME",
                      "width" => "100%"
                  ],
                  [
                      "caption" => "Language",
                      "attribute_alias" => "LOCALE",
                      "width" => "100%"
                  ]
              ],
              "buttons" => [
                  [
                      "caption" => $this->getApp()->getTranslator()->translate('CONTEXT.USER.MY_ACCOUNT'),
                      "action_alias" => "exface.Core.ShowUserAccountDialog"
                  ],
                  [
                    "action_alias" => "exface.Core.Logout",
                    "align" => EXF_ALIGN_OPPOSITE
                  ]
                ]
            ];
            
            // Use prefill instead of hard-coded values to allow behaviors to hook in and
            // add fields to the context widget. Reading data for the prefill is done below
            // after the IF.
            $prefillData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
            $prefillData->getFilters()->addConditionFromString('USERNAME', $token->getUsername());
            
        // when user is not logged in, build context with login button and message that user is not logged in    
        } else {
            $uxon = [
              "widget_type" => "Form",
              "object_alias" => "exface.Core.USER",
              "widgets" => [
                    [
                        "widget_type" => "Message",
                        "value" => $coreApp->getTranslator()->translate('CONTEXT.USER.NOT_LOGGED_IN_HINT'),
                        "width" => "100%"
                    ],[
                        "widget_type" => "Display",
                        "caption" => "Language",
                        "attribute_alias" => "LOCALE",
                        "width" => "100%",
                        "value" => $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale()
                    ]
              ],
              "buttons" => [
                [
                    "action_alias" => "exface.Core.ShowLoginDialog",
                    "visibility" => WidgetVisibilityDataType::PROMOTED
                ]
              ]
            ];
            $prefillData = null;
        }
        
        $uxon_object = UxonObject::fromAnything($uxon);
        $form = WidgetFactory::createFromUxonInParent($container, $uxon_object);  
        if ($prefillData !== null) {
            $prefillData = $form->prepareDataSheetToPrefill($prefillData);
            $prefillData->dataRead();
            $form->prefill($prefillData);
        }
        
        $container->addWidget($form);
        
        return $container;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.USER.NAME');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        return $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getInitials();
    }
    
    /**
     * The user context resides in the user scope.
     *
     * {@inheritDoc}
     * @see \exface\Core\Contexts\ObjectBasketContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->getContext()->getScopeUser();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getScope()
     */
    public function getScope()
    {
        return $this->getDefaultScope();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::setScope()
     */
    public function setScope(ContextScopeInterface $context_scope)
    {
        if ($context_scope != $this->getDefaultScope()){
            throw new ContextRuntimeError($this, 'Cannot use context scope "' . $context_scope->getName() . '" for context "' . $this->getAliasWithNamespace() . '": only user context scope allowed!');
        }
        return parent::setScope($context_scope);
    }
}