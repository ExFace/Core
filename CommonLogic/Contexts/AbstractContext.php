<?php
namespace exface\Core\CommonLogic\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;
use exface\Core\Widgets\Container;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\Constants\Colors;

/**
 * This is a basic implementation of common context methods intended to be used
 * as the base for "real" contexts.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractContext implements ContextInterface
{

    private $name_resolver = null;

    private $scope = null;

    private $alias = null;
    
    private $indicator = null;
    
    private $indicator_color = null;
    
    private $icon = null;
    
    private $name = null;
    
    private $context_bar_visibility = null;
    
    /**
     * @deprecated use ContextFactory instead
     * @param \exface\Core\CommonLogic\Workbench $exface
     */
    public function __construct(NameResolverInterface $name_resolver)
    {
        $this->name_resolver = $name_resolver;
    }
    
    /**
     * 
     * @return NameResolverInterface
     */
    public function getNameResolver()
    {
        return $this->name_resolver;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getNameResolver()->getNamespace();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNameResolver()->getAliasWithNamespace();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getScope()
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::setScope()
     */
    public function setScope(ContextScopeInterface $context_scope)
    {
        $this->scope = $context_scope;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeWindow();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getNameResolver()->getWorkbench();
    }

    /**
     * Returns a serializable UXON object, that represents the current contxt, 
     * thus preparing it to be saved in a session, cookie, database or whatever 
     * is used by a context scope.
     * 
     * What exactly ist to be saved, strongly depends on the context type: an 
     * action context needs an acton alias and, perhaps, a data backup, a filter 
     * context needs to save it's filters conditions, etc. In any case, the 
     * serialized version should contain enough data to restore the context 
     * completely afterwards, but also not to much data in order not to consume 
     * too much space in whatever stores the respective context scope.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->getWorkbench()->createUxonObject();
    }

    /**
     * Restores a context from it's UXON representation.
     * 
     * The input is whatever export_uxon_object() produces for this context type.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     * @return ContextInterface
     */
    public function importUxonObject(UxonObject $uxon)
    {
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->getNameResolver()->getAlias();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getIndicator()
     */
    public function getIndicator()
    {
        return $this->indicator;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::setIndicator()
     */
    public function setIndicator($indicator)
    {
        $this->indicator = $indicator;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getVisibility()
     */
    public function getVisibility()
    {
        if (is_null($this->context_bar_visibility)){
            $this->setVisibility(ContextInterface::CONTEXT_BAR_SHOW_IF_NOT_EMPTY);
        }
        return $this->context_bar_visibility;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::setVisibility()
     */
    public function setVisibility($value)
    {
        if (! defined('static::CONTEXT_BAR_' . mb_strtoupper($value))) {
            throw new ContextRuntimeError($this, 'Invalid context_bar_visibility value "' . $value . '" for context "' . $this->getAliasWithNamespace() . '"!');
        }
        $this->context_bar_visibility = constant('static::CONTEXT_BAR_' . mb_strtoupper($value));
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::isEmpty()
     */
    public function isEmpty()
    {
        return $this->active;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {
        return $container;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getIcon()
     */
    public function getIcon()
    {
        return $this->icon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::setIcon()
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::setName()
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getColor()
     */
    public function getColor()
    {
        if (is_null($this->indicator_color)){
            return Colors::DEFAULT;
        }
        return $this->indicator_color;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::setColor()
     */
    public function setColor($indicator_color)
    {
        $this->indicator_color = $indicator_color;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextInterface::getApp()
     */
    public function getApp(){
        return $this->getWorkbench()->getApp($this->getNameResolver()->getAppAlias());
    }
 
}
?>