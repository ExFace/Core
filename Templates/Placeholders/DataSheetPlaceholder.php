<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Templates\BracketHashStringTemplateRenderer;

/**
 * Replaces a placeholder with data sheet rows rendered from a provided row template.
 * 
 * important onfiguration options:
 * 
 * - `data_sheet` to load the data 
 * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. 
 * `[#~data:some_attribute#]`, `[#~data:=Formula()#]`.
 * - `data_placeholders` to add nested structures of the same type
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
 *      "template": "Order number: [#~input:ORDERNO#] <br><br> <table><tr><th>Product</th><th>Price</th></tr>[#positions#]</table>",
 *      "data_placeholders": {
 *          "positions": {
 *              "row_template": "<tr><td>[#~data:product#]</td><td>[#~data:price#]</td></tr>",
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
 * ### Adding nested data like discounts per order position
 * 
 * Extending the example above, we can add further nested data using a similar technique: adding
 * `data_placeholders` to the `positions` placeholder configuration allows us to load data for
 * every position. In this example, we will load discounts per position and list them for every
 * order position.
 * 
 * ```
 * {
 *      "template": "Order number: [#~input:ORDERNO#] <br><br> <table><tr><th>Product</th><th>Price</th><th>Discounts</th></tr>[#positions#]</table>",
 *      "data_placeholders": {
 *          "positions": {
 *              "row_template": "<tr><td>[#~data:product#]</td><td>[#~data:price#]</td><td>Dicsounts</td></tr>",
 *              "data_sheet": {
 *                  "object_alias": "my.App.ORDER_POSITION",
 *                  "filters": {
 *                      "operator": "AND",
 *                      "conditions": [
 *                          {"expression": "ORDER__NO", "comparator": "==", "value": "[#~input:ORDERNO#]"}
 *                      ]
 *                  }
 *              },
 *              "data_placeholders": {
 *                  "discounts": {
 *                      "row_template": "<div>- [#~data:name#]: [#~data:value#]</div>",
 *                      "data_sheet": {
 *                          "object_alias": "my.App.ORDER_POSITION_DISCOUNT",
 *                          "filters": {
 *                              "operator": "AND",
 *                              "conditions": [
 *                                  {"expression": "ORDER_POSITION", "comparator": "==", "value": "[#positions:ID#]"}
 *                              ]
 *                          }
 *                      }
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
class DataSheetPlaceholder implements PlaceholderResolverInterface, iCanBeConvertedToUxon
{    
    use ImportUxonObjectTrait;
    
    private $placeholder = null;
    
    private $dataSheet = null;
    
    private $rowTpl = '';
    
    private $rowRenderer = null;
    
    private $workbench = null;
    
    private $prefix = null;
    
    /**
     * 
     * @param string $placeholder to replace 
     * @param UxonObject $configUxon for this placeholder
     * @param BracketHashStringTemplateRenderer $configRenderer template renderer for placeholders in the config
     * @param TemplateRendererInterface $baseRowRenderer
     * @param string $dataRowPlaceholdersPrefix
     */
    public function __construct(string $placeholder, UxonObject $configUxon, BracketHashStringTemplateRenderer $configRenderer, TemplateRendererInterface $baseRowRenderer, string $dataRowPlaceholdersPrefix = '~data:')
    {
        $this->workbench = $configRenderer->getWorkbench();
        $this->rowRenderer = $baseRowRenderer;
        $this->prefix = $dataRowPlaceholdersPrefix;
        $this->placeholder = $placeholder;
        
        $configRenderer->setIgnoreUnknownPlaceholders(true);
        $renderedUxon = UxonObject::fromJson($configRenderer->render($configUxon->toJson()));
        $this->importUxonObject($renderedUxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $phValsSheet = $this->getDataSheet();
        $phValsSheet->dataRead();
        $dataPhsUxon = $this->getDataPlaceholdersUxon();
        
        $phRowTpl = $this->getRowTemplate();
        $phRendered = '';
        foreach (array_keys($phValsSheet->getRows()) as $rowNo) {
            $currentRowRenderer = $this->rowRenderer->copy();
            
            if ($dataPhsUxon !== null) {
                // Prepare a renderer for the data_placeholders config
                $dataTplRenderer = new BracketHashStringTemplateRenderer($phValsSheet->getWorkbench());
                $dataTplRenderer->addPlaceholder(
                    (new DataRowPlaceholders($phValsSheet, $rowNo, $this->placeholder . ':'))
                    ->setFormatValues(false)
                    ->setSanitizeAsUxon(true)
                );
                $dataTplRenderer->addPlaceholder(
                    (new FormulaPlaceholders($phValsSheet->getWorkbench(), $phValsSheet, $rowNo))
                    ->setSanitizeAsUxon(true)
                );
                
                // Create group-resolver with resolvers for every data_placeholder and use
                // it as the default resolver for the input row renderer
                $phResolver = new PlaceholderGroup();
                foreach ($dataPhsUxon->getPropertiesAll() as $ph => $phConfig) {
                    $phResolver->addPlaceholderResolver(new DataSheetPlaceholder($ph, $phConfig, $dataTplRenderer, $currentRowRenderer));
                }
                $currentRowRenderer->setDefaultPlaceholderResolver($phResolver);
            }
            
            $currentRowRenderer->addPlaceholder(new DataRowPlaceholders($phValsSheet, $rowNo, $this->prefix));
            $phRendered .= $currentRowRenderer->render($phRowTpl);
        }
        
        return [$this->placeholder => $phRendered];
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
     * @return DataSheetPlaceholder
     */
    protected function setDataSheet(UxonObject $value) : DataSheetPlaceholder
    {
        $this->dataSheet = DataSheetFactory::createFromUxon($this->workbench, $value);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getRowTemplate() : string
    {
        return $this->rowTpl;
    }
    
    /**
     * The template string for each row of the loaded data
     * 
     * @uxon-property row_template
     * @uxon-type string
     * @uxon-template <tr><td>[#~data:some_attribute#]</td></tr>
     * @uxon-required true
     * 
     * @param string $value
     * @return DataSheetPlaceholder
     */
    protected function setRowTemplate(string $value) : DataSheetPlaceholder
    {
        $this->rowTpl = $value;
        return $this;
    }
    
    protected function getDataPlaceholdersUxon() : ?UxonObject
    {
        return $this->dataPlaceholders;
    }
    
    /**
     * Additional data placeholders to be provided to the template
     *
     * @uxon-property data_placeholders
     * @uxon-type \exface\Core\Templates\Placeholders\DataSheetPlaceholders[]
     * @uxon-template {"": {"row_template": "", "data_sheet": {"object_alias": "", "columns": [{"attribute_alias": ""}], "filters": {"operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}}}}
     *
     * @param UxonObject $value
     * @return DataSheetPlaceholder
     */
    protected function setDataPlaceholders(UxonObject $value) : DataSheetPlaceholder
    {
        $this->dataPlaceholders = $value;
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }
}