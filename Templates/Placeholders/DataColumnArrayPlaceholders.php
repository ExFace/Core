<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Templates\BracketHashStringTemplateRenderer;

/**
 * Replaces a placeholder with an array of values from a column of a data sheet.
 * 
 * Important onfiguration options:
 * 
 * - `data_sheet` to load the data 
 * 
 * ## Examples 
 * 
 * ### Printing positions of an order
 * 
 * Concider the following example for a simple order print template in HTML. Assume, that the `ORDER` 
 * object has its order number in the `ORDERNO` attribute and multiple related `ORDER_POSITION`
 * objects, that are to be printed as an HTML `<table>`. The below configuration creates a data
 * placeholder for the positions and defines a data sheet to load them. the `[#positions#]` placeholder
 * in the main `template` will be replaced by a concatennation of rendered `row_template`s. The
 * `data_sheet` used in the configuration of the data placeholder contains placeholders itself: in this
 * case, the `[#~input:ORDERNO#]`, with will be replace by the order number from the input data before
 * the sheet is read. The `row_template` now may contain global placeholders and those from it's
 * data placeholder rows - prefixed with `~data:`.
 * 
 * ```
 * {
 *      "template": "vendor/app/Templates/Order.xlsx",
 *      "data_placeholders": {
 *          "positions": {
 *              "data_sheet": {
 *                  "object_alias": "my.App.ORDER_POSITION",
 *                  "filters": {
 *                      "operator": "AND",
 *                      "conditions": [
 *                          {"expression": "ORDER__NO", "comparator": "==", "value": "[#~input:ORDERNO#]"}
 *                      ]
 *                  }
 *              }
 *          }
 *      }
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 */
class DataColumnArrayPlaceholders 
    extends AbstractPlaceholderResolver 
    implements iCanBeConvertedToUxon
{    
    use ImportUxonObjectTrait;

    private $dataSheet = null;
    
    private $rowRenderer = null;
    
    private $workbench = null;
    
    /**
     * 
     * @param string $placeholder to replace 
     * @param UxonObject $configUxon for this placeholder
     * @param BracketHashStringTemplateRenderer $configRenderer template renderer for placeholders in the config
     * @param TemplateRendererInterface $baseRowRenderer
     * @param string $dataRowPlaceholdersPrefix
     */
    public function __construct(UxonObject $configUxon, BracketHashStringTemplateRenderer $configRenderer, TemplateRendererInterface $baseRowRenderer, string $dataRowPlaceholdersPrefix = null)
    {
        $this->workbench = $configRenderer->getWorkbench();
        $this->rowRenderer = $baseRowRenderer;
        $this->setPrefix($dataRowPlaceholdersPrefix ?? '');
        
        $configRenderer->setIgnoreUnknownPlaceholders(true);
        $renderedUxon = UxonObject::fromJson($configRenderer->render($configUxon->toJson()));
        $this->importUxonObject($renderedUxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {     
        $phValsSheet = $this->getDataSheet();
        $phVals = [];

        $colPhs = $this->filterPlaceholders($placeholders);
        $phCols = [];
        foreach ($colPhs as $ph) {
            $phCols[$ph] = $phValsSheet->getColumns()->addFromExpression($this->stripPrefix($ph));
        }
        $phValsSheet->dataRead();
        
        foreach ($phCols as $ph => $col) {
            $phVals[$ph] = $col->getValues();
        }
        
        return $phVals;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getDataSheet() : DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     * UXON model of the data to read with placholders
     * 
     * @uxon-property data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "columns": [{"attribute_alias": ""}], "filters": {"operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}}
     * 
     * @param UxonObject $value
     * @return DataColumnArrayPlaceholders
     */
    protected function setDataSheet(UxonObject $value) : DataColumnArrayPlaceholders
    {
        $this->dataSheet = DataSheetFactory::createFromUxon($this->workbench, $value);
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }
}