<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\MetamodelUsernamePasswordAuthToken;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Form;

/**
 * Performs authentication via user data stored in the metamodel.
 * 
 * @author Andrej Kabachnik
 *
 */
class MetamodelAuthenticator extends SymfonyAuthenticator
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool {
        return $token instanceof MetamodelUsernamePasswordAuthToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::createLoginForm()
     */
    protected function createLoginForm(Form $form) : Form
    {
        $container = parent::createLoginForm($form);
        $container->addWidget(WidgetFactory::createFromUxonInParent($container, new UxonObject([
                'attribute_alias' => 'AUTH_TOKEN_CLASS',
                'value' => '\\' . MetamodelUsernamePasswordAuthToken::class,
                'widget_type' => 'InputHidden'
            ]
        )));
        
        return $container;
    }
}