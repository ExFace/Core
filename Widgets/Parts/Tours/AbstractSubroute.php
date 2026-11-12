<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\InputComboTable;

/**
 * 
 */
abstract class AbstractSubroute implements TourSubrouteInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private WidgetInterface $widget;
    
    /**
     * Creates a lookup subroute for an InputComboTable using the supplied step texts.
     *
     * @param WidgetInterface $widget
     * @param UxonObject $uxon
     */
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        if ($widget instanceof InputComboTable) {
            $this->widget = $widget;
            $this->importUxonObject($uxon);
        } else {
            throw new WidgetConfigurationError($widget, 'InputComboTableSubroute can only be used with InputComboTable widgets.');
        }
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
    
    public function getTitle() : ?string
    {
        return 'Lookup';
    }
    
    public static function getUxonTemplate() : UxonObject
    {
        return UxonObject::fromArray([
            'lookup_button' => '',
            'lookup_header' => '',
            'lookup_search_button' => '',
            'lookup_table' => '',
            'lookup_selection' => '',
            'lookup_apply_button' => '',
        ]);
    }
}