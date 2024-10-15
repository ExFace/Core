<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Templates\AbstractPlaceholderResolver;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\DataTypes\StringDataType;

/**
 * Replaces a placeholder with data sheet rows rendered from a provided row template.
 * 
 * Inside the row template you can access the loaded data via `[#thisPlacehlderName:ATTR#]` where
 * `thisPlacehlderName` is the name of the data placeholder and `ATTR` is the data column name in
 * the loaded data sheet.
 * 
 * Important onfiguration options:
 * 
 * - `data_sheet` to load the data 
 * - `outer_template` to wrap all the rendered `row_templates` adding a common title or similar
 * - `outer_template_if_empty` - replaces `outer_template` if there are no data rows
 * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. 
 * `[#thisPlacehlderName:some_attribute#]`, `[#thisPlacehlderName:=Formula()#]`.
 * - `row_delimiter` to define a custom separator between row templates - e.g. a comma
 * - `row_template_if_empty` - replaces the row template if there are no data rows, but you
 * wish to still render the `outer_template` (e.g. table headers or so).
 * - `data_placeholders` to add nested structures of the same type
 * 
 * ### Handling empty data
 * 
 * There are different ways to handle empty data:
 * 
 * - by default all placeholders will be empty, so the entire `outer_template`
 * will disappear if there is no data.
 * - `outer_template_if_empty` will replace the entire outer template
 * - `row_template_if_empty` will use the regular `outer_template`, but
 * will place a "fake" row generated from this template inside of it
 * instead of regular rows. 
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
 *              "row_template": "<tr><td>[#positions:product#]</td><td>[#positions:price#]</td></tr>",
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
 *  {
 *      "template": "Order number: [#~input:ORDERNO#] <br><br> <table><tr><th>Product</th><th>Price</th><th>Discounts</th></tr>[#positions#]</table>",
 *      "data_placeholders": {
 *          "positions": {
 *              "row_template": "<tr><td>[#positions:product#]</td><td>[#positions:price#]</td><td>Dicsounts</td></tr>",
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
 *                      "row_template": "<div>- [#positions:name#]: [#positions:value#]</div>",
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
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 */
class DataSheetPlaceholder
    extends AbstractPlaceholderResolver
    implements iCanBeConvertedToUxon
{    
    use ImportUxonObjectTrait;
    
    private $placeholder = null;
    
    private $dataSheet = null;
    
    private $rowTpl = '';
    
    private $rowTplIfEmpty = null;
    
    private $rowRenderer = null;
    
    private $workbench = null;
    
    private $outerTemplate = null;

    private $outerTemplateIfEmpty = null;
    
    private $rowDelimiter = '';
    
    /**
     * 
     * @param string $placeholder to replace 
     * @param UxonObject $configUxon for this placeholder
     * @param BracketHashStringTemplateRenderer $configRenderer template renderer for placeholders in the config
     * @param TemplateRendererInterface $baseRowRenderer
     * @param string $dataRowPlaceholdersPrefix
     */
    public function __construct(string $placeholder, UxonObject $configUxon, BracketHashStringTemplateRenderer $configRenderer, TemplateRendererInterface $baseRowRenderer, string $dataRowPlaceholdersPrefix = null)
    {
        $this->workbench = $configRenderer->getWorkbench();
        $this->rowRenderer = $baseRowRenderer;
        $this->placeholder = $placeholder;
        $this->prefix = $dataRowPlaceholdersPrefix ?? $this->placeholder . ':';
        
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
        $noData = $phValsSheet->isEmpty(false);
        $dataPhsUxon = $this->getDataPlaceholdersUxon();
        $phRowTpl = $this->getRowTemplate();
        
        // Before the new syntax using `[#prefix:` for data placeholders, there was a hardcoded special
        // placeholder `[#~data:`. Here we check, if this legacy syntax is used. If so, a special row
        // placeholder renderer will be added in addition to the normal one in the foreach() below. 
        $legacyPrefix = '~data:';
        $legacyPrefixFound = stripos($phRowTpl, '[#' . $legacyPrefix) !== false;
        
        // Render row placeholders (only happens if data was found!)
        $rowsRendered = [];
        foreach (array_keys($phValsSheet->getRows()) as $rowNo) {
            $currentRowRenderer = $this->rowRenderer->copy();
            $currentRowRenderer->addPlaceholder(new DataRowPlaceholders($phValsSheet, $rowNo, $this->prefix));
            if ($legacyPrefixFound === true) {
                $currentRowRenderer->addPlaceholder(new DataRowPlaceholders($phValsSheet, $rowNo, $legacyPrefix));
            }
            
            if ($dataPhsUxon !== null) {
                // Prepare a renderer for the data_placeholders config
                $dataTplRenderer = new BracketHashStringTemplateRenderer($phValsSheet->getWorkbench());
                // In the config of the nested renderer there will be access the data sheet of this 
                // resolver via `[#thisPlacehlderName:...#]`
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
                $dataPhsResolverGroup = new PlaceholderGroup();
                // Make sure to copy the row renderer before passing it to nested data resolvers! Otherwise we
                // will run into infinite recursion if at least one placeholder cannt be resolved: the current
                // row renderer will get the resolver group as default placeholder resolver, which will also have
                // affect on the base renderer (if it is used directly), etc.
                $dataPhsBaseRenderer = $currentRowRenderer->copy();
                foreach ($dataPhsUxon->getPropertiesAll() as $ph => $phConfig) {
                    $dataPhsResolverGroup->addPlaceholderResolver(new DataSheetPlaceholder($ph, $phConfig, $dataTplRenderer, $dataPhsBaseRenderer));
                }
                $currentRowRenderer->setDefaultPlaceholderResolver($dataPhsResolverGroup);
            }
            
            $rowsRendered[] = $currentRowRenderer->render($phRowTpl);
        }

        $outerTpl = $this->getOuterTemplate();
        if ($noData) {
            $outerTplIfEmpty = $this->getOuterTemplateIfEmpty();
            $rowTplIfEmpty = $this->getRowTemplateIfEmpty();
            switch (true) {
                case $outerTplIfEmpty !== null && $rowTplIfEmpty !== null:
                    throw new UnexpectedValueException('Cannot use `row_template_if_empty` and `outer_template_if_empty` at the same time in template placeholder "' . $this->placeholder . '"');
                case $outerTplIfEmpty !== null:
                    $phRendered = $outerTplIfEmpty;
                    break;
                case $rowTplIfEmpty !== null:
                    if (null !== $outerTpl) {
                        $phRendered = StringDataType::replacePlaceholder($outerTpl, '~rows', $rowTplIfEmpty); 
                    } else {
                        $phRendered = $rowTplIfEmpty;
                    }
                    break;
                default:
                    $phRendered = '';
            }
        } else {
            $phRendered = implode($this->getRowDelimiter(), $rowsRendered);
            if (null !== $outerTpl) {
                $phRendered = StringDataType::replacePlaceholder($outerTpl, '~rows', $phRendered); 
            }
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
     * Available placeholders:
     * 
     * - `thisPlacehlderName:COLUMN` - any column from the data defined within this placeolder
     * - `thisPlacehlderName:=Concat(ATTR1, ', ', ATTR2)` - a formula evaluated 
     * in the context of the current data row
     * 
     * @uxon-property row_template
     * @uxon-type string
     * @uxon-template <tr><td>[#thisPlacehlderName:some_attribute#]</td></tr>
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

    /**
     * 
     * @return string
     */
    protected function getRowTemplateIfEmpty() : ?string
    {
        return $this->rowTplIfEmpty;
    }

    /**
     * The template string to generate an empty row if there is no data.
     * 
     * NOTE: if this template is defined, the `outer_template` will still
     * be used with empty data! Make sure, that this row template fits into
     * the outer template - e.g. if using and HTML table with 3 columns in the 
     * outer template, the `row_template_if_empty` should still be a row and
     * span the right number of columns: e.g. `<tr><td colspan="3">No data</td></tr>`.
     * 
     * There are different ways to handle empty data:
     * 
     * - by default all placeholders will be empty, so the entire `outer_template`
     * will disappear if there is no data.
     * - `outer_template_if_empty` will replace the entire outer template
     * - `row_template_if_empty` will use the regular `outer_template`, but
     * will place a "fake" row generated from this template inside of it
     * instead of regular rows. 
     * 
     * @uxon-property row_template_if_empty
     * @uxon-type string
     * @uxon-template <tr><td colspan="">No data</td></tr>
     * 
     * @param string $value
     * @return \exface\Core\Templates\Placeholders\DataSheetPlaceholder
     */
    protected function setRowTemplateIfEmpty(string $value) : DataSheetPlaceholder
    {
        $this->rowTplIfEmpty = $value;
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
     * @return string|NULL
     */
    protected function getOuterTemplate() : ?string
    {
        return $this->outerTemplate;
    }
    
    /**
     * A wrapper around all the data rows - e.g. a title, that would only be shown if there is at least one row.
     * 
     * IMPORTANT: make sure to include the placeholder `[#~rows#]` at the point, which will be
     * replaced by all the rendered `row_template`s. 
     * 
     * @uxon-property outer_template
     * @uxon-type string
     * @uxon-template [#~rows#]
     * 
     * @param string $value
     * @return DataSheetPlaceholder
     */
    protected function setOuterTemplate(string $value) : DataSheetPlaceholder
    {
        $this->outerTemplate = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getOuterTemplateIfEmpty() : ?string
    {
        return $this->outerTemplateIfEmpty;
    }
    
    /**
     * A replacement for `outer_template` in case there is no data
     * 
     * There are different ways to handle empty data:
     * 
     * - by default all placeholders will be empty, so the entire `outer_template`
     * will disappear if there is no data.
     * - `outer_template_if_empty` will replace the entire outer template
     * - `row_template_if_empty` will use the regular `outer_template`, but
     * will place a "fake" row generated from this template inside of it
     * instead of regular rows. 
     * 
     * @uxon-property outer_template_if_empty
     * @uxon-type string
     * @uxon-template No data
     * 
     * @param string $value
     * @return DataSheetPlaceholder
     */
    protected function setOuterTemplateIfEmpty(string $value) : DataSheetPlaceholder
    {
        $this->outerTemplateIfEmpty = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getRowDelimiter() : string
    {
        return $this->rowDelimiter;
    }
    
    /**
     * Separator between rows - e.g. a comma, if needed.
     * 
     * @uxon-property row_delimiter
     * @uxon-type string
     * 
     * @param string $value
     * @return DataSheetPlaceholder
     */
    protected function setRowDelimiter(string $value) : DataSheetPlaceholder
    {
        $this->rowDelimiter = $value;
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