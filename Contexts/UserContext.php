<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\DataTypes\LocaleDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;
use exface\Core\DataTypes\WidgetVisibilityDataType;

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
        if ($user->isUserAnonymous() === true) {
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
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        $uxon = null;
        
        //when user is logged in, build context with user details and logout button
        if ($user->isUserAnonymous() === false){
            $icon = Icons::SIGN_OUT;
            $uxon = [
              "widget_type" => "Form",
              "height" => "100%",
              "object_alias" => "exface.Core.USER",
              "columns_in_grid" => "1",
              "widgets" => [
                [
                    "widget_type" => "Message",
                    "value" => "You are logged in.",
                    "width" => "100%"
                ],
                [
                    "widget_type" => "Display",
                    "attribute_alias" => "USERNAME",
                    "width" => "100%",
                    "value" => $user->getUsername()
                ],
                [
                    "widget_type" => "Display",
                    "attribute_alias" => "FIRST_NAME",
                    "width" => "100%",
                    "value" => $user->getFirstName()
                ],
                [
                    "widget_type" => "Display",
                    "attribute_alias" => "LAST_NAME",
                    "width" => "100%",
                    "value" => $user->getLastName()
                ],
                [
                    "widget_type" => "Display",
                    "caption" => "Language",
                    "attribute_alias" => "LOCALE",
                    "width" => "100%",
                    "value" => $user->getLocale()
                ]
              ],
              "buttons" => [
                  [
                      "action" => [
                          "alias" => "exface.Core.ShowUserAccountDialog",
                          "input_data_sheet" => [
                              "object_alias" => "exface.Core.USER",
                              "rows" => [
                                  [
                                      "UID" => $user->getUid()
                                  ]
                              ] 
                          ]
                      ]
                  ],
                  [
                    "action" => [
                        "alias" => "exface.Core.GoToUrl",
                        "url" => $this->getWorkbench()->getCMS()->buildUrlToSiteRoot() . "/login.html?webloginmode=lo"
                    ],
                    "caption" => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.LOGOUT.NAME'),
                    "icon" => $icon,
                    "align" => EXF_ALIGN_OPPOSITE
                ]
              ]
            ];
        // when user is not logged in, build context with login button and message that user is not logged in    
        } else {
            $icon = Icons::SIGN_IN;
            $uxon = [
              "widget_type" => "Form",
              "object_alias" => "exface.Core.USER",
              "widgets" => [
                    [
                        "widget_type" => "Message",
                        "value" => "You are not logged in. Please Login!",
                        "width" => "100%"
                    ],[
                        "widget_type" => "Display",
                        "caption" => "Language",
                        "attribute_alias" => "LOCALE",
                        "width" => "100%",
                        "value" => $user->getLocale()
                    ]
              ],
              "buttons" => [
                [
                    "action" => [
                        "alias" => "exface.Core.GoToUrl",
                        "url" => $this->getWorkbench()->getCMS()->buildUrlToSiteRoot() . "/login.html"
                    ],
                    "caption" => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.LOGIN.NAME'),
                    "icon" => $icon,
                    "visibility" => WidgetVisibilityDataType::PROMOTED
                ]
              ]
            ];
        }
        
        $uxon_object = UxonObject::fromAnything($uxon);
        $form = WidgetFactory::createFromUxon($container->getPage(), $uxon_object, $container);      
        
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