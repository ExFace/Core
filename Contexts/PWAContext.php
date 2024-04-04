<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\Widgets\Container;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class PWAContext extends AbstractContext
{
    /**
     * The favorites context resides in the user scope.
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
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon() : ?string
    {
        return Icons::WIFI;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.OFFLINE.NAME');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getColor()
     */
    public function getColor()
    {
        return Colors::DEFAULT_COLOR;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {
        // TDOD
        return $container;
    }
}