<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;

/**
 * This trait enables an exception to output debug information specific to data sheet mappers.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataSheetMapperExceptionTrait {
    
    use DataSheetExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $mapper = null;

    public function __construct(DataSheetInterface $data_sheet, DataSheetMapper $mapper, $message, $alias = null, $previous = null)
    {
        parent::__construct($data_sheet, $message, $alias, $previous);
        $this->setMapper($mapper);
    }

    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        
        // TODO
        
        return $debug_widget;
    }
    
    /**
     * @return DataSheetMapperInterface
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param DataSheetMapperInterface $mapper
     */
    public function setMapper(DataSheetMapperInterface $mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

}
?>