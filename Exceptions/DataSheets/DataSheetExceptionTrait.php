<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\CommonLogic\DataSheets\DataSheet;
use exface\Core\Facades\DocsFacade;
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
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface::getDataSheet()
     */
    public function getDataSheet() : DataSheetInterface
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

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = parent::getLinks();
        $links['DataSheet structure'] = DocsFacade::buildUrlToDocsForUxonPrototype(DataSheet::class);
        $obj = $this->getDataSheet()->getMetaObject();
        $links['Metaobject ' . $obj->__toString()] = DocsFacade::buildUrlToDocsForMetaObject($obj->getAliasWithNamespace());
        return $links;
    }
}