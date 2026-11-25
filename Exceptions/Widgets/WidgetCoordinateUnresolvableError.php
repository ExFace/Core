<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Widgets\InputComboTable;

/**
 * Exception thrown when a widget cannot resolve a value coordinate.
 *
 * Includes the requested coordinate and the widget's available columns in the debug details.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetCoordinateUnresolvableError extends WidgetConfigurationError
{
    private $coordinate;

    /**
     * Retains the target widget and coordinate for debugging resolution failures.
     *
     * @param WidgetInterface $widget
     * @param WidgetLinkCoordinateInterface $coordinate
     * @param string $message
     * @param string|null $alias
     * @param \Throwable|null $previous
     */
    public function __construct(WidgetInterface $widget, WidgetLinkCoordinateInterface $coordinate, $message, $alias = null, $previous = null)
    {
        parent::__construct($widget, $message, $alias, $previous);
        $this->coordinate = $coordinate;
    }

    /**
     * @return WidgetLinkCoordinateInterface
     */
    public function getCoordinate(): WidgetLinkCoordinateInterface
    {
        return $this->coordinate;
    }

    /**
     * {@inheritDoc}
     * @see WidgetConfigurationError::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = parent::createDebugWidget($debug_widget);
        foreach ($debug_widget->getTabs() as $tab) {
            if ($tab->getCaption() === 'Coordinate') {
                return $debug_widget;
            }
        }
        $tab = $debug_widget->createTab();
        $tab->setCaption('Coordinate');
        $tab->setWidgets(new UxonObject([
            [
                "widget_type" => "Markdown",
                "height" => '100%',
                "width" => '100%',
                "hide_caption" => true,
                "value" => $this->buildMarkdownForCoordinate($this->getCoordinate()) 
                    . "\n\n" . $this->buildMarkdownForWidget($this->getWidget())
            ]
        ]));
        $debug_widget->addTab($tab);
        return $debug_widget;
    }

    /**
     * @param WidgetLinkCoordinateInterface $coordinate
     * @return string
     */
    protected function buildMarkdownForCoordinate(WidgetLinkCoordinateInterface $coordinate): string
    {
        $markdown = "## Coordinate `{$coordinate->__toString()}`\n\n";
        $markdown .= "- Column: `" . $coordinate->getColumnCoordinate() . "`\n";
        $markdown .= "- Row: `" . ($coordinate->getRowCoordinate() ?? ' ') . "`\n";
        $markdown .= "- Aggregator: `" . ($coordinate->getAggregator() ?? ' ') . "`\n";
        return $markdown;
    }

    /**
     * @param WidgetInterface $widget
     * @return string
     */
    protected function buildMarkdownForWidget(WidgetInterface $widget): string
    {
        $markdown = "## Widget `{$widget->getWidgetType()}`\n\n";
        $markdown .= "- Object: " . $widget->getMetaObject()->__toString() . "\n";
        
        $markdown .= "\nSupported coordinates:\n\n";
        foreach ($widget->getLinkCoordinates() as $coordinate) {
            $markdown .= "- `$coordinate`\n";
        }

        $columns = match (true) {
            $widget instanceof iHaveColumns => $widget->getColumns(),
            $widget instanceof InputComboTable => $widget->getTable()->getColumns(),
            $widget instanceof iUseData => $widget->getData()->getColumns(),
            default => [],
        };
        if (! empty($columns)) {
            $markdown .= "\nColumns:\n\n";
            foreach ($columns as $column) {
                $markdown .= "- `" . $column->getExpression()->__toString() . "`\n";
            }
        }
        
        return $markdown;
    }
}