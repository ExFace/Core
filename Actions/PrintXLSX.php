<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Templates\BracketHashXlsxTemplateRenderer;
use exface\Core\Templates\Placeholders\DataColumnArrayPlaceholders;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Templates\Placeholders\PlaceholderGroup;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\TranslationPlaceholders;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * This action prints data using a XLSX template with any number of sub-sheets.
 *  
 * The template itself is an XLSX file that you have to write with an external editor.
 * Place it within the file structure of the app you wish to use it in, for example:
 * 
 * `vendor/[project]/[app]/Dokumente/Export-Templates/[template]`
 * 
 * Then add a new action definition under `Administration -> Metamodel -> Printing Templates`, with
 * `PrintXLSX` as its ActionPrototype. You can find an example on how to write the UXON file further below.
 * 
 * 
 * ## Template placeholders
 * 
 * 
 * The template can contain the following placeholders:
 *  
 * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
 * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key` 
 * from the given app
 * - `[#~input:column_name#]` - will be replaced by the value from `column_name` of the input data sheet
 * of the action
 * - `[#=Formula()#]` - will evaluate the formula (e.g. `=Now()`) in the context each row of the input data
 * - `[#~file:name#]` and `[#~file:name_without_ext#]` - well be replaced by the name of the rendered file
 * with our without extension.
 * - `[#~data:column_name:AGGREGATOR#]` - aggregates the specified column.
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
 * `[#dataPlaceholderNameSome_attribute#]`, `[#dataPlaceholderName=Formula()#]`.
 * - nested `data_placeholders` to use inside each data placeholder
 *  
 * ## Writing the UXON
 *  
 * While the formatting and layout are defined by the template file, the data you wish to print is specified and
 * formatted within the UXON definition. Consequently, you should write your UXON as though you wanted to create a
 * `DataTable`. Think carefully about what data you need and how you wish to format it, as the print action simply
 * parses whatever it receives into the template.
 * 
 * Consider the following example for a simple table export to XLSX. The property `template` tells the action where to
 * find the template you have written, while `filename` will be the name of the file created by this action. You may
 * apply placeholders to the filename.
 * 
 * Next up we define any number of `data_placeholders`. These are essentially lookups that help the action understand
 * what data you wish to fill in. It then pre-loads all the necessary data, making it available for further processing.
 * This feature is very powerful, as it allows you to pull data from other tables and apply filters and sorters to it.
 * You can insert this data into your template by using type name you specified as a placeholder. In this example we
 * called it `data_placeholder_example`. To reference that in our template we would use
 * `[#data_placeholder_example:column_name#]`.
 *  
 * ```
 * 
 * {
 *      "template": "vendor/[project]/[app]/Dokumente/Export-Templates/[template]",
 *      "filename": "Order [#~input:ORDERNO#].xlsx",
 *      "data_placeholders": {
 *          "data_placeholder_example": {
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
 * @author Andrej Kabachnik, Georg Bieger
 *
 */
class PrintXLSX extends PrintTemplate
{
    private $template = null;
    
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::FILE_EXCEL_O);
    }

    /**
     * Returns an array of the form [file_path => rendered_template].
     *
     * @param DataSheetInterface $inputData
     * @param bool               $preview
     * @return string[]
     */
    public function renderTemplate(DataSheetInterface $inputData, bool $preview = false) : array
    {
        $contents = [];
        $tplPath = $this->getTemplate();
        // If the template is empty, there is nothing to print (e.g. when simulating a prefill via PrefillModel)
        if ($tplPath === null || $tplPath === '') {
            return [];
        }
        
        $dataPhsUxon = $this->getDataPlaceholdersUxon();
        
        $baseRenderer = new BracketHashXlsxTemplateRenderer($this->getWorkbench());
        $baseRenderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench(), '~config:'));
        $baseRenderer->addPlaceholder(new TranslationPlaceholders($this->getWorkbench(), '~translate:'));

        $path = $this->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $tplPath;

        try {
            $readerXlsx = IOFactory::createReaderForFile($path);
        } catch (\Exception $e) {
            throw new ActionRuntimeError($this, 'Unable to open file: ' . $path, null, $e);
        }

        foreach (array_keys($inputData->getRows()) as $rowNo) {
            // Extend base renderer with placeholders for the current input data row
            $currentRowRenderer = $baseRenderer->copy();
            $currentRowRenderer->addPlaceholder(new DataRowPlaceholders($inputData, $rowNo, '~input:'));
            $currentRowRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench(), $inputData, $rowNo));
            // Create string renderer for file path.
            $filePathRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
            $filePathRenderer->addPlaceholdersViaArray($currentRowRenderer->getResolvers());

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

            // Ensure unique file paths.
            $basePath = explode('.', $filePath = $this->getFilePathAbsolute($filePathRenderer));
            for($i = 1; array_key_exists($filePath, $contents); $i++) {
                $filePath = $basePath[0].'_'.$i.'.'.$basePath[1];
            }

            // Add resolvers for file path.
            $currentRowRenderer->addPlaceholder(new ArrayPlaceholders([
                'name' => FilePathDataType::findFileName($filePath, true),
                'name_without_ext' => FilePathDataType::findFileName($filePath, false)
            ], '~file:'));
            
            // Render the template for the current input row.
            try {
                // Load template sheet.
                $tplSpreadsheet = $readerXlsx->load($path);

                // Render the template for the current input row. Spreadsheets are always passed by reference.
                $currentRowRenderer->render($tplSpreadsheet);

                // Save file.
                $filePath = explode('.', $filePath)[0].$this->getFileExtension($preview);
                $writerType = $this->getWriterType($preview);
                $writer = IOFactory::createWriter($tplSpreadsheet, $writerType);
                if($preview) {
                    $writer->writeAllSheets();
                }
                $writer->save($filePath);

                // Unset spreadsheet to avoid memory leaks.
                $tplSpreadsheet->disconnectWorksheets();
                unset($tplSpreadsheet);

                // Retrieve binary data.
                $contents[$filePath] = file_get_contents($filePath);
            } catch (\Exception $e) {
                throw new ActionRuntimeError($this, 'Unable to render template: '.$e->getMessage(), null, $e);
            }
        }

        return $contents;
    }

    /**
     * @param bool $preview
     * @return string
     */
    protected function getFileExtension(bool $preview = false) : string
    {
        if($preview) {
            return '.html';
        } else {
            $mimeType = $this->getMimeType();
            return empty($mimeType) ? '.xlsx' : '.'.$mimeType;
        }
    }

    /**
     * @param bool $preview
     * @return string
     */
    protected function getWriterType(bool $preview) : string
    {
        if($preview) {
            return IOFactory::WRITER_HTML;
        } else {
            return match ($this->getMimeType()) {
                'html' => IOFactory::WRITER_HTML,
                'xls' => IOFactory::WRITER_XLS,
                'csv' => IOFactory::WRITER_CSV,
                'ods' => IOFactory::WRITER_ODS,
                default => IOFactory::WRITER_XLSX,
            };
        }
    }

    /**
     * Choose the file type the rendered template should be exported as.
     *
     * @uxon-property mime_type
     * @uxon-type [xlsx,html,xls,csv,ods]
     *
     * @param string $mimeType
     * @return $this
     */
    public function setMimeType(string $mimeType) : PrintTemplate
    {
        return parent::setMimeType($mimeType);
    }

    /**
     * @return string
     */
    public function getMimeType(): string
    {
        return parent::getMimeType() ?? 'xlsx';
    }


    /**
     * 
     * @return string
     */
    protected function getTemplate() : string
    {
        return $this->template;
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

    /**
     * @param DataSheetInterface $inputData
     * @return string[]
     */
    public function renderPreviewHTML(DataSheetInterface $inputData) : array
    {
        return $this->renderTemplate($inputData, true);
    }
}