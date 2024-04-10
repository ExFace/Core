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
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\DataTypes\StringDataType;

/**
 * This context displays a menu with URLs to be opened in a browser-dialog (e.g. as quick access to app docs)
 * or in a new tab of the browser.
 * 
 * Example configuration:
 * 
 * ```
 *  {
 * 		"context_scope": "Installation",
 * 		"context_alias": "exface.Core.DocsContext",
 * 		"visibility": "show_allways",
 * 		"menu_items": {
 * 			"App docs": "api/docs/",
 * 			"App-desiner tutorial": "api/docs/exface/Core/Docs/Tutorials/BookClub_walkthrough/index.md",
 * 			"Customizing the context bar": "api/docs/exface/Core/Docs/Administration/Configuration/Customizing_the_context_bar.md",
 *          "App doc PDF": {"url": "vendor/customer/app/Docs/documentation.pdf", "open_in_new_window": true}
 *      }
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class DocsContext extends AbstractContext
{
    use ImportUxonObjectTrait;
    
    private $menuItems = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon() : ?string
    {
        return Icons::QUESTION_CIRCLE;
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
        $buttonsUxon = new UxonObject();
        
        foreach ($this->getMenuItems() as $title => $item) {
            if (is_array($item) === false) {
                $url = $item;
            } else {
                $url = $item['url'];
                $newWindow = $item['open_in_new_window'] ?? false;
            }
            if ($newWindow === true) {
                $buttonsUxon->append(new UxonObject([
                    'caption' => $title,
                    'icon' => Icons::EXTERNAL_LINK_SQUARE,
                    'action' => [
                        'alias' => 'exface.Core.GoToUrl',
                        'url' => $url,
                        'open_in_new_window' => true,
                        'input_rows_min' => 0
                    ]
                ]));
            } else {
                $buttonsUxon->append(new UxonObject([
                    'caption' => $title,
                    'icon' => Icons::BOOK,
                    'action' => [
                        'alias' => 'exface.Core.ShowDialog',
                        'dialog' => [
                            'caption' => $title,
                            'cacheable' => false,
                            'height' => '80%',
                            'width' => '2',
                            'widgets' => [
                                [
                                    'widget_type' => 'Browser',
                                    'url' => $url
                                ]
                            ]
                        ]
                    ]
                ]));
            }
        }
        
        $menu = WidgetFactory::createFromUxonInParent($container, new UxonObject([
            'widget_type' => 'Menu',
            'caption' => $this->getName(),
            'buttons' => $buttonsUxon
        ])); 
        
        $container->addWidget($menu);
        
        return $container;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWHELPDIALOG.NAME');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        return null;
    }
    
    /**
     * The user context resides in the user scope.
     *
     * {@inheritDoc}
     * @see \exface\Core\Contexts\ObjectBasketContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->getContext()->getScopeInstallation();
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
            throw new ContextRuntimeError($this, 'Cannot use context scope "' . $context_scope->getName() . '" for context "' . $this->getAliasWithNamespace() . '": only installation-scope allowed!');
        }
        return parent::setScope($context_scope);
    }
    
    protected function getMenuItems() : array
    {
        return $this->menuItems;
    }
    
    /**
     * Names and URLs for the buttons in the menu
     * 
     * @uxon-property menu_items
     * @uxon-type object
     * @uxon-template {"":""}
     * 
     * @param UxonObject $value
     * @return DocsContext
     */
    protected function setMenuItems(UxonObject $value) : DocsContext
    {
        $this->menuItems = $value->toArray();
        return $this;
    }
}