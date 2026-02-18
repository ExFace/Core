<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;

class TourStep implements TourStepInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private WidgetInterface $widget;
    private ?string $title = null;
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->importUxonObject($uxon);
    }

    /**
     * {@inheritDoc}
     * @see TourStepInterface::getTitle()
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The title of the step
     * 
     * @uxon-property title
     * @uxon-type string
     * @uxon-required true
     * @uxon-translatable true
     * 
     * @param string $title
     * @return TourStepInterface
     */
    protected function setTitle(string $title): TourStepInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see TourStepInterface::getBody()
     */
    public function getBody(): string
    {
        // TODO: Implement getBody() method.
        return 'Test test test';
    }

    /**
     * {@inheritDoc}
     * @see TourStepInterface::getWaypoints()
     */
    public function getWaypoints(): array
    {
        // TODO: Implement getWaypoints() method.
        return ['news'];
    }
    
    /**
     * @inheritDoc
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * @inheritDoc
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
}