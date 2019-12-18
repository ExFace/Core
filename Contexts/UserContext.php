<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;

/**
 * The UserContext shows the logged in User or a message that the user is not logged in and provides a Login or Logout button.
 *
 * @author Ralf Mulansky
 *        
 */
class UserContext extends AbstractContext
{

    public function getIcon()
    {
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        if ($user->isUserAnonymous() === true) {
            return Icons::USER_SECRET;
        }
        return Icons::USER;
    }
    
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
        
        if ($user->isUserAnonymous() === false){
            $uxon = <<<UXON

{
  "widget_type": "Form",
  "object_alias": "exface.Core.USER",
  "widgets": [
    {
        "widget_type": "Message",
        "value": "You are logged in.",
        "width": "100%"
    },
    {
        "readonly": true,
        "attribute_alias": "USERNAME",
        "width": "100%"
    },
    {
        "readonly": true,
        "attribute_alias": "FIRST_NAME",
        "width": "100%"
    },
    {
        "readonly": true,
        "attribute_alias": "LAST_NAME",
        "width": "100%"
    }
  ],
  "buttons": [
    {
      "action": {
        "alias": "exface.Core.GoToUrl",
        "url": "http://localhost/exface/login.html?webloginmode=lo"
      },
      "caption": "Logout"
    }
  ]
}

UXON;
            
        } else {
            $uxon = <<<UXON

{
  "widget_type": "Form",
  "object_alias": "exface.Core.USER",
  "widgets": [
    {
      "widget_type": "Message",
      "value": "You are not logged in. Please Login!",
        "width": "100%"
    }
  ],
  "buttons": [
    {
      "action": {
        "alias": "exface.Core.GoToUrl",
        "url": "http://localhost/exface/login.html"
      },
      "caption": "Login"
    }
  ]
}

UXON;
        }
        
        $uxon_object = UxonObject::fromAnything($uxon);
        $form = WidgetFactory::createFromUxon($container->getPage(), $uxon_object);
        
        if ($user->isUserAnonymous() === false) {
            $children = $form->getChildren();
            foreach ($children as $child) {
                if (! $child instanceof iShowSingleAttribute){
                    continue;
                }
                $attribute_alias = $child->getAttributeAlias();
                switch ($attribute_alias) {
                    case 'USERNAME':
                        $child->setValue($user->getUsername());
                        break;
                    case 'FIRST_NAME':
                        $child->setValue($user->getFirstName());
                        break;
                    case 'LAST_NAME':
                        $child->setValue($user->getLastName());
                        break;
                }
            }
        }
       
        
        $container->addWidget($form);
        
        return $container;
    }
    
    public function getName(){
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.USER.NAME');
    }
}
?>