<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Base class for auto-generated, widget-specific tour subroutes.
 *
 * A subroute belongs to the widget of a tour step and can be customized via the `subroute` property
 * of that step. Subclasses define the UXON properties for their substeps and may restrict the
 * supported widget types by validating the widget before calling this constructor.
 */
abstract class AbstractSubroute implements TourSubrouteInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private WidgetInterface $widget;
    
    private UxonObject $uxon;
    
    /**
     * Creates a subroute for the given widget and imports its UXON configuration.
     *
     * @param WidgetInterface $widget
     * @param UxonObject $uxon
     */
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->uxon = $uxon;
        $this->importUxonObject($uxon);
    }
    
    /**
     * {@inheritDoc}
     * @see TourSubrouteInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    /**
     * {@inheritDoc}
     * 
     * The id is computed from the subroute type, the widget id and the subroute configuration. This
     * makes it the same in every request, that instantiates the widget - e.g. the request for the page
     * and the request for a lookup dialog of that page.
     * 
     * @see TourSubrouteInterface::getTourId()
     */
    public function getTourId() : string
    {
        return 'subroute_' . md5($this->getSubtourType() . '|' . $this->widget->getId() . '|' . $this->uxon->toJson());
    }

    /**
     * {@inheritDoc}
     * @see TourSubrouteInterface::getSubtourType()
     *
     * @return string
     */
    public function getSubtourType(): string
    {
        return PhpClassDataType::findClassNameWithoutNamespace($this);
    }
    
    /**
     * {@inheritDoc}
     * @see TourSubrouteInterface::getTitle()
     */
    public function getTitle() : string
    {
        return $this->widget->getCaption() ?? $this->getSubtourType();
    }
}