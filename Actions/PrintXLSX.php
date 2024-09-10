<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Filesystem\LocalFileInfo;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Templates\BracketHashExcelTemplateRenderer;
use exface\Core\Templates\Placeholders\DataColumnArrayPlaceholders;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Templates\Placeholders\PlaceholderGroup;
use exface\Core\Templates\Placeholders\DataSheetPlaceholder;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\TranslationPlaceholders;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use exface\Core\Interfaces\Actions\iRenderTemplate;
use exface\Core\Interfaces\Actions\iUseTemplate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * This action prints data using a text-based template (e.g. HTML)
 * 
 * The template can either be set inside the action property `template` or read from
 * a file specified by the `template_path` (absolute or relative to the vendor folder).
 * 
 * ## Template placeholders
 * 
 * The template can contain the following placehodlers:
 * 
 * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
 * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key` 
 * from the given app
 * - `[#~input:column_name#]` - will be replaced by the value from `column_name` of the input data sheet
 * of the action
 * - `[#=Formula()#]` - will evaluate the formula (e.g. `=Now()`) in the context each row of the input data
 * - `[#~file:name#]` and `[#~file:name_without_ext#]` - well be replaced by the name of the rendered file
 * with our without extension.
 * - additional custom placeholders can be defined in `data_placeholders` - see below.
 * 
 * ## Data placeholders
 * 
 * In addition to the general placeholders above, additional data can be loaded into the table:
 * e.g. positions of an order in addition to the actual order data, which is the input of the action.
 * 
 * Each entry in `data_placeholders` consists of a custom placeholder name (to be used in the main `template`) 
 * and a configuration for its contents:
 * 
 * - `data_sheet` to load the data 
 * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. 
 * `[#dataPlaceholderNamesome_attribute#]`, `[#dataPlaceholderName=Formula()#]`.
 * - nested `data_placeholders` to use inside each data placeholder
 * 
 * ## Example 
 * 
 * Concider the following example for a simple order print template in HTML. Assume, that the `ORDER` 
 * object has its order number in the `ORDERNO` attribute and multiple related `ORDER_POSITION`
 * objects, that are to be printed as an HTML `<table>`. The below configuration creates a data
 * placeholder for the positions and defines a data sheet to load them. the `[#positions#]` placeholder
 * in the main `template` will be replaced by a concatennation of rendered `row_template`s. The
 * `data_sheet` used in the configuration of the data placeholder contains placeholders itself: in this
 * case, the `[#~input:ORDERNO#]`, with will be replace by the order number from the input data before
 * the sheet is read. The `row_template` now may contain global placeholders and those from it's
 * data placeholder rows - prefixed with the respective placeholder name.
 * 
 * ```
 * {
 *      "template": "Order number: [#~input:ORDERNO#] <br><br> <table><tr><th>Product</th><th>Price</th></tr>[#positions#]</table>",
 *      "filename": "Order [#~input:ORDERNO#].html",
 *      "data_placeholders": {
 *          "positions": {
 *              "row_template": "<tr><td>[#positions:product#]</td><td>[#positions:price#]</td></tr>",
 *              "data_sheet": {
 *                  "object_alias": "my.App.ORDER_POSITION",
 *                  "columns": [
 *                      {"attribute_alias": "product"},
 *                      {"attribute_alias": "price"}
 *                  ],
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
 *
 */
class PrintXLSX extends PrintTemplate
{
    private $dataPlaceholders = null;

    private $template = null;
    
    
    /**
     * Returns an array of the form [file_path => rendered_template].
     * 
     * @param DataSheetInterface $inputData
     * @return string[]
     */
    public function renderTemplate(DataSheetInterface $inputData) : array
    {
        $contents = [];
        $tplPath = $this->getTemplate();
        // If the template is empty, there is nothing to print (e.g. when simulating a prefill via PrefillModel)
        if ($tplPath === null || $tplPath === '') {
            return [];
        }
        
        $dataPhsUxon = $this->getDataPlaceholdersUxon();
        
        $baseRenderer = new BracketHashExcelTemplateRenderer($this->getWorkbench());
        $baseRenderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench(), '~config:'));
        $baseRenderer->addPlaceholder(new TranslationPlaceholders($this->getWorkbench(), '~translate:'));
        
        foreach (array_keys($inputData->getRows()) as $rowNo) {
            // Extend base renderer with placeholders for the current input data row
            $currentRowRenderer = $baseRenderer->copy();
            $currentRowRenderer->addPlaceholder(new DataRowPlaceholders($inputData, $rowNo, '~input:'));
            $currentRowRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench(), $inputData, $rowNo));
            
            if ($dataPhsUxon !== null) {
                // Prepare a renderer for the data_placeholders config
                $dataTplRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
                $dataTplRenderer->addPlaceholder(
                    (new DataRowPlaceholders($inputData, $rowNo, '~input:'))
                    ->setFormatValues(false)
                    ->setSanitizeAsUxon(true)
                );
                $dataTplRenderer->addPlaceholder(
                    (new FormulaPlaceholders($this->getWorkbench(), $inputData, $rowNo))
                    ->setSanitizeAsUxon(true)
                );
                
                // Create group-resolver with resolvers for every data_placeholder and use
                // it as the default resolver for the input row renderer
                $dataPhsResolverGroup = new PlaceholderGroup();
                $dataPhsBaseRenderer = $currentRowRenderer->copy();
                foreach ($dataPhsUxon->getPropertiesAll() as $ph => $phConfig) {
                    // Add a resolver for the data-placeholder: e.g. `[#positions:id#]`, `[#positions:name#]`
                    $dataPhsResolverGroup->addPlaceholderResolver(new DataColumnArrayPlaceholders($phConfig, $dataTplRenderer, $dataPhsBaseRenderer, $ph . ':'));
                }
                $currentRowRenderer->setDefaultPlaceholderResolver($dataPhsResolverGroup);
            }
            // placeholders for the resulting file
            $filePath = $this->getFilePathAbsolute($currentRowRenderer);
            $currentRowRenderer->addPlaceholder(new ArrayPlaceholders([
                'name' => FilePathDataType::findFileName($filePath, true),
                'name_without_ext' => FilePathDataType::findFileName($filePath, false)
            ], '~file:'));
            
            // Render the template for the current input row
            $mainTplRendered = $currentRowRenderer->render($tplPath);
            if (array_key_exists($filePath, $contents)) {
                $contents[$filePath] .= $mainTplRendered;
            } else {
                $contents[$filePath] = $mainTplRendered;
            }
        }
        return $contents;
    }

    
    /**
     * 
     * @return string
     */
    protected function getFileExtensionDefault() : string
    {
        return '.xlsx';
    }
    
    /**
     * 
     * @return string|null
     */
    public function getMimeType() : ?string
    {
        return parent::getMimeType() ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
    
    /**
     * 
     * @return string
     */
    protected function getTemplate() : string
    {
        return $this->template;
    }

    protected function getTemplateReader() : Spreadsheet
    {
        $path = $this->getTemplateFileInfo()->getPathAbsolute();
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        return $spreadsheet;
    }

    protected function getTemplateFileInfo() : FileInfoInterface
    {
        $pathAbs = $this->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $this->getTemplate();
        return new LocalFileInfo(new \SplFileInfo($pathAbs));
    }
    
    /**
     * Path to the Excel template file
     * 
     * @uxon-property template
     * @uxon-type string
     * 
     * @param string $value
     * @return PrintTemplate
     */
    public function setTemplate(string $value) : PrintTemplate
    {
        $this->template = $value;
        return $this;
    }

    protected function getExcelWriter(Spreadsheet $spreadsheet) : IWriter
    {
        $writer = IOFactory::createWriter($spreadsheet, 'TODO');
        return $writer;
    }
}