<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;

/**
 * This trait enables an exception to output data sheet specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataSheetExceptionTrait {
    
    #TODO function to censor columns with sensitive data
    
    use ExceptionTrait {
		createDebugWidget as createDebugWidgetViaExceptionTrait;
	}

    private $data_sheet = null;

    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataSheet($data_sheet);
    }

    /**
     *
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    /**
     *
     * @param DataSheetInterface $sheet            
     * @return \exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait
     */
    public function setDataSheet(DataSheetInterface $sheet)
    {
        $this->data_sheet = $sheet;
        return $this;
    }

    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->createDebugWidgetViaExceptionTrait($debug_widget);
        return $this->getDataSheet()->createDebugWidget($debug_widget);
    }
}