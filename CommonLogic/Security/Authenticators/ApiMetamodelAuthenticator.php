<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Widgets\Form;

/**
 * Authenticates API requests via username and password - does not provide a login widget
 * 
 * @author Andrej Kabachnik
 *
 */
class ApiMetamodelAuthenticator extends MetamodelAuthenticator
{
    protected function createLoginForm(Form $form) : Form
    {
        return $form;
    }
}