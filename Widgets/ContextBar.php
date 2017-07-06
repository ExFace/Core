<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UiPage;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\Widgets\WidgetLogicError;

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
class ContextBar extends ButtonBar
{
    private $context_widget_map = [];
    
    protected function init()
    {
        $this->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.CONTEXT_BASE_OBJECT'));
        $this->setInputWidget($this);
        foreach ($this->getWorkbench()->getConfig()->getOption('CONTEXT_BAR.VISIBILITY') as $context_selector => $visibility){
            $visibility = strtolower($visibility);
            if ($visibility == ContextInterface::CONTEXT_BAR_DISABED){
                continue;
            }
            
            $context_selector_parts = explode(':', $context_selector);
            $context_scope_name = $context_selector_parts[0];
            $context_alias = $context_selector_parts[1];
            
            try {
                $btn = $this->createButtonForContext($this->getWorkbench()->context()->getScope($context_scope_name)->getContext($context_alias));
                
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
                        throw new WidgetLogicError($this, 'Invalid context bar visibility "' . $visibility . '" set for context "' . $context_alias . '"');
                }
            
                $this->addButton($btn);
            } catch (ExceptionInterface $e){
                $this->getWorkbench()->getLogger()->alert($e->getMessage(), [], $e);
            } catch (\Throwable $e){
                $this->getWorkbench()->getLogger()->alert($e->getMessage(), ["exception" => $e]);
            }
        }                
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
        
        $btn->getAction()->setContextAlias($context->getAlias());
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
        return $this->getId() . UiPage::WIDGET_ID_SEPARATOR . $context->getScope()->getName() . $context->getAlias();
    }
    
    public function getContextForButton(Button $button)
    {
        return $this->context_widget_map[$button->getId()];
    }
    
}
?>