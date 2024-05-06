<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\CommonLogic\UxonObject;

/**
 * This trait enables an exception to output widget specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait WidgetExceptionTrait {
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $widget = null;

    public function __construct(WidgetInterface $widget, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setWidget($widget);
    }

    /**
     * Returns the widget, that produced the error.
     *
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function getWidget()
    {
        return $this->widget;
    }

    /**
     * Sets the widget, that produced the error.
     *
     * @param WidgetInterface $widget            
     * @return \exface\Core\Exceptions\Widgets\WidgetExceptionTrait
     */
    public function setWidget(WidgetInterface $widget)
    {
        $this->widget = $widget;
        return $this;
    }

    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        if ($debug_widget->findChildById('widget_uxon_tab') === false) {
            $uxon_tab = $debug_widget->createTab();
            $uxon_tab->setId('widget_uxon_tab');
            $uxon_tab->setCaption('Widget');
            
            $widget = $this->getWidget();
            $widgetUxon = $widget->exportUxonObjectOriginal();
            
            $parent = $widget;
            $parentUxon = new UxonObject();
            while ($parent->hasParent() && $parentUxon->isEmpty()) {
                $parent = $parent->getParent();
                $parentUxon = $parent->exportUxonObjectOriginal();
            }
            
            if (($trigger = $widget->getParentByClass(iTriggerAction::class)) && $trigger->hasAction()) {
                $action = $trigger->getAction();
                $actionInfo = $action->getAliasWithNamespace() . ' (' . $action->getName() . ')';
            } else {
                $actionInfo = 'exface.Core.ShowWidget (root)';
            }
            
            $tabContents = <<<MD

- Type `{$widget->getWidgetType()}`
- ID: `{$widget->getId()}`
- Called by action: `{$actionInfo}`

## Widget UXON

```
{$widgetUxon->toJson(true)}
```

## Parent widget UXON

```
{$parentUxon->toJson(true)}
```

MD;
            $uxon_tab->addWidget(WidgetFactory::createFromUxonInParent($uxon_tab, new UxonObject([
                'widget_type' => 'Markdown',
                'value' => $tabContents,
                'width' => '100%',
                'height' => '100%'
            ])));
            $debug_widget->addTab($uxon_tab);
        }
        return $debug_widget;
    }
}
?>