<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Exceptions\DataMappingExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\UxonObject;

/**
 * Exception thrown if a data mapping fails.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataMappingFailedError extends RuntimeException implements DataMappingExceptionInterface
{
    private $mapping = null;
    
    private $fromSheet = null;
    
    private $toSheet = null;
    
    /**
     * 
     * @param DataMappingInterface $mapping
     * @param DataSheetInterface $fromSheet
     * @param DataSheetInterface $toSheet
     * @param string $message
     * @param string|NULL $alias
     * @param \Throwable|NULL $previous
     */
    public function __construct(DataMappingInterface $mapping, DataSheetInterface $fromSheet, DataSheetInterface $toSheet, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->mapping = $mapping;
        $this->fromSheet = $fromSheet;
        $this->toSheet = $toSheet;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface::getMapper()
     */
    public function getMapping() : DataMappingInterface
    {
        return $this->mapping;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getFromDataSheet() : DataSheetInterface
    {
        return $this->fromSheet;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getToDataSheet() : DataSheetInterface
    {
        return $this->toSheet;
    }
    
    /**
     * 
     * @return DataSheetMapperInterface
     */
    public function getMapper() : DataSheetMapperInterface
    {
        return $this->mapping->getMapper();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = parent::createDebugWidget($debug_widget);
        
        $tab = $debug_widget->createTab();
        $tab->setCaption('Data-Mapping');
        $tab->setNumberOfColumns(2);
        $tab->setWidgets(new UxonObject([
            [
                'widget_type' => 'Html',
                'width' => 2,
                'height' => 'auto',
                'html' => '<h2>Mapping configuration</h2><p>' . get_class($this->getMapping()) .  '</p><pre>' . $this->getMapping()->exportUxonObject()->toJson(true) . '</pre>'
            ],
            [
                'widget_type' => 'Html',
                'width' => 1,
                'height' => 'auto',
                'html' => '<h2>From-data</h2><pre>' . $this->getFromDataSheet()->getCensoredDataSheet()->exportUxonObject()->toJson(true) . '</pre>'
            ],
            [
                'widget_type' => 'Html',
                'width' => 1,
                'height' => 'auto',
                'html' => '<h2>To-data</h2><pre>' . $this->getToDataSheet()->getCensoredDataSheet()->exportUxonObject()->toJson(true) . '</pre>'
            ]
        ]));
        $debug_widget->addTab($tab);
        return $debug_widget;
    }
}