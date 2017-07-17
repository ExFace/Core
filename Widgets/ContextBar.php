<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UiPage;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Exceptions\Contexts\ContextAccessDeniedError;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * The context bar shows information about the current context of the workbench.
 * 
 * Each page always has exactly one context bar. It is a special widget showing
 * icons (buttons) for every active context in the system. In a sense it is
 * similar to the system tray in Windows.
 * 
 * Every context is represented by a button showing a popup. The contents of
 * that popup is defined by the context itself.
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextBar extends Toolbar
{
    private $context_widget_map = [];
    
    protected function init(){
        parent::init();
        $this->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.CONTEXT_BASE_OBJECT'));
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\ButtonGroup::getButtons()
     */
    public function getButtons()
    {
        if (count(parent::getButtons()) == 0){
            $this->importUxonObject($this->getWorkbench()->getConfig()->getOption('CONTEXTBAR'));
        }
        return parent::getButtons();
    }
    
    /**
     * 
     * @param UxonObject[] $context_uxon_objects
     * @return ContextBar
     */
    public function setContexts(array $context_uxon_objects){
        foreach ($context_uxon_objects as $uxon){
            $visibility = strtolower($uxon->getProperty('visibility'));
            if ($visibility == ContextInterface::CONTEXT_BAR_DISABED){
                continue;
            }
            
            if ($uxon->getProperty('restrict_to_admins') && ! $this->getWorkbench()->context()->getScopeUser()->isUserAdmin()){
                $this->getWorkbench()->getLogger()->info('Not adding context "' . $uxon->getProperty('context_scope') . ':' . $uxon->getProperty('context_alias') . '" to ContextBar: it is accessible for admins only!');
                continue;
            } else {
                $uxon->unsetProperty('restrict_to_admins');
            }
            
            if ($uxon->getProperty('restrict_to_authenticated') && $this->getWorkbench()->context()->getScopeUser()->isUserAnonymous()){
                $this->getWorkbench()->getLogger()->info('Not adding context "' . $uxon->getProperty('context_scope') . ':' . $uxon->getProperty('context_alias') . '" to ContextBar: it is accessible for logged in users only!');
                continue;
            } else {
                $uxon->unsetProperty('restrict_to_authenticated');
            }
            
            $context = $this->getWorkbench()->context()->getScope($uxon->getProperty('context_scope'))->getContext($uxon->getProperty('context_alias'));
            $uxon->unsetProperty('context_scope');
            $uxon->unsetProperty('context_alias');
            
            // IDEA Make contexts totally configurable by importing the UXON from
            // the config into each context. This would need the contexts to be
            // compatible with the ImportUxonTrait though. Currently many contexts
            // like ObjectBasketContext, ActionContext, etc. use non-standard
            // UXON Objects.
            // $context->importUxonObject($uxon);
            
            try {
                $btn = $this->createButtonForContext($context);
                
                switch ($visibility){
                    case ContextInterface::CONTEXT_BAR_SHOW_IF_NOT_EMPTY:
                        if ($btn->getAction()->getWidget()->countWidgetsVisible() == 0){
                            $btn->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
                            break;
                        }
                    case ContextInterface::CONTEXT_BAR_SHOW_ALLWAYS:
                        $btn->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
                        break;
                    case ContextInterface::CONTEXT_BAR_HIDE_ALLWAYS:
                        $btn->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
                        break;
                    default:
                        throw new WidgetLogicError($this, 'Invalid context bar visibility "' . $visibility . '" set for context "' . $context->getAlias() . '"');
                }
                
                $this->addButton($btn);
            } catch (ContextAccessDeniedError $e){
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::INFO);
            } catch (\Throwable $e){
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }
        return $this;
    }
    
    /**
     * 
     * @param ContextInterface $context
     * @return \exface\Core\Widgets\Button
     */
    protected function createButtonForContext(ContextInterface $context)
    {   
        /* @var $btn \exface\Core\Widgets\Button */
        $btn = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this)
        ->setId($this->createButtonIdFromContext($context))
        ->setActionAlias('exface.Core.ShowContextPopup')
        ->setHint($context->getName())
        ->setIconName($context->getIcon())
        ->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.CONTEXT_BASE_OBJECT'));
        
        $btn->getAction()->setContextAlias($context->getAliasWithNamespace());
        $btn->getAction()->setContextScope($context->getScope()->getName());
        
        $this->context_widget_map[$btn->getId()] = $context;
        
        return $btn;
    }
    
    /**
     * 
     * @param ContextInterface $context
     * @return string
     */
    protected function createButtonIdFromContext(ContextInterface $context){
        return $this->getId() . UiPage::WIDGET_ID_SEPARATOR . str_replace('.', '', $context->getScope()->getName() . $context->getAliasWithNamespace());
    }
    
    /**
     * 
     * @param Button $button
     * @return ContextInterface|null
     */
    public function getContextForButton(Button $button)
    {
        return $this->context_widget_map[$button->getId()];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'Button';
    }
    
    /**
     * The input widget of the context bar is the context bar itself.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Button::getInputWidget()
     */
    public function getInputWidget()
    {
        return $this;
    }
    
}
?>