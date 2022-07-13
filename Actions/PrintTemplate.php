<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\AbstractAction;
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
 * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. `[#~data:some_attribute#]`, `[#~data:=Formula()#]`..
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
 * data placeholder rows - prefixed with `~data:`.
 * 
 * ```
 * {
 *      "template": "Order number: [#~input:ORDERNO#] <br><br> <table><tr><th>Product</th><th>Price</th></tr>[#positions#]</table>",
 *      "filename": "Order [#~input:ORDERNO#].html",
 *      "data_placeholders": {
 *          "positions": {
 *              "row_template": "<tr><td>[#~data:product#]</td><td>[#~data:price#]</td></tr>",
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
class PrintTemplate extends AbstractAction
{
    private $downloadable = true;
    
    private $filename = null;
    
    private $mimeType = null;
    
    private $pathname = null;
    
    private $templatePath = null;
    
    private $template = null;
    
    private $placeholders = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::DOWNLOAD);
        $this->setInputRowsMin(1);
    }

    /**
     * 
     */
    public function isDownloadable() : bool
    {
        return $this->downloadable;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        $contents = $this->renderTemplate($inputData);
        
        foreach ($contents as $filePath => $fileContents) {
            file_put_contents($filePath, $fileContents);
        }
        $result = ResultFactory::createFileResult($task, $filePath);
        
        return $result;
    }
    
    /**
     * Returns an array of the form [file_path => rendered_template].
     * 
     * @param DataSheetInterface $inputData
     * @return string[]
     */
    protected function renderTemplate(DataSheetInterface $inputData) : array
    {
        $contents = [];
        $mainTpl = $this->getTemplate();
        $mainPhUxon = $this->getDataPlaceholdersUxon();
        
        $baseRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $baseRenderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench(), '~config:'));
        $baseRenderer->addPlaceholder(new TranslationPlaceholders($this->getWorkbench(), '~translate:'));
        
        foreach (array_keys($inputData->getRows()) as $rowNo) {
            // Extend base renderer with placeholders for the current input data row
            $inputRowRenderer = $baseRenderer->copy();
            $inputRowRenderer->addPlaceholder(new DataRowPlaceholders($inputData, $rowNo, '~input:'));
            $inputRowRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench(), $inputData, $rowNo));
            
            if ($mainPhUxon) {
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
                $phResolver = new PlaceholderGroup();
                foreach ($mainPhUxon->getPropertiesAll() as $ph => $phConfig) {
                    $phResolver->addPlaceholderResolver(new DataSheetPlaceholder($ph, $phConfig, $dataTplRenderer, $inputRowRenderer));
                }
                $inputRowRenderer->setDefaultPlaceholderResolver($phResolver);
            }
            // placeholders for the resulting file
            $filePath = $this->getFilePathAbsolute($inputRowRenderer);
            $inputRowRenderer->addPlaceholder(new ArrayPlaceholders([
                'name' => FilePathDataType::findFileName($filePath, true),
                'name_without_ext' => FilePathDataType::findFileName($filePath, false)
            ], '~file:'));
            
            // Render the template for the current input row
            $mainTplRendered = $inputRowRenderer->render($mainTpl);
            if (array_key_exists($filePath, $contents)) {
                $contents[$filePath] .= $mainTplRendered;
            } else {
                $contents[$filePath] = $mainTplRendered;
            }
        }
        return $contents;
    }

    /**
     * Set to FALSE to prevent direct downloading of the exported file (i.e. just export, no download).
     * 
     * @uxon-property downloadable
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $true_or_false
     * @return PrintTemplate
     */
    public function setDownloadable(bool $true_or_false) : PrintTemplate
    {
        $this->downloadable = $true_or_false;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getFilename(TemplateRendererInterface $tplRenderer) : string
    {
        if ($this->filename === null){
            return 'print_' . date('Y_m_d_his', time()) . $this->getFileExtensionDefault();
        }
        return FilePathDataType::sanitizeFilename($tplRenderer->render($this->filename));
    }
    
    /**
     * 
     * @return string
     */
    protected function getFileExtensionDefault() : string
    {
        return '.html';
    }

    /**
     * Name of the file to generate (including the extension)
     * 
     * The `filename` may contain the following placeholders: 
     * 
     * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
     * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key` 
     * from the given app
     * - `[#~input:column_name#]` - will be replaced by the value from `column_name` of the input data sheet
     * of the action
     * - `[#=Formula()#]` - will evaluate the formula (e.g. `=Now()`) in the context each row of the input data
     * 
     * If no file name is specified, it will be generated from the export time: 
     * e.g. `print_2018_10_22_162259.html`.
     * 
     * @uxon-property filename
     * @uxon-type string
     * 
     * @param string $filename
     * @return PrintTemplate
     */
    public function setFilename(string $filename) : PrintTemplate
    {
        $this->filename = $filename;
        $this->pathname = null;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getMimeType() : ?string
    {
        return $this->mimeType;
    }
    
    /**
     * Explicitly specifies a mime type for the download.
     * 
     * @uxon-property mime_type
     * @uxon-type string
     * 
     * @param string $mimeType
     * @return PrintTemplate
     */
    public function setMimeType(string $mimeType) : PrintTemplate
    {
        $this->mimeType = $mimeType;
        return $this;
    }  
    
    /**
     * Returns the absolute path to the file.
     *
     * @return string
     */
    protected function getFilePathAbsolute(TemplateRendererInterface $tplRenderer) : string
    {
        if (is_null($this->pathname)) {
            $filemanager = $this->getWorkbench()->filemanager();
            $this->pathname = Filemanager::pathJoin([
                $filemanager->getPathToCacheFolder(),
                $this->getFilename($tplRenderer)
            ]);
        }
        return $this->pathname;
    }
    
    /**
     * 
     * @return string
     */
    protected function getTemplatePathAbsolute() : string
    {
        return $this->templatePath;
    }
    
    /**
     * Path to a template file instad of `template` (absolute or relative to the vendor folder)
     * 
     * @uxon-property template_path
     * @uxon-type string
     * 
     * @param string $value
     * @return PrintTemplate
     */
    public function setTemplatePath(string $value) : PrintTemplate
    {
        $this->templatePath = FilePathDataType::isAbsolute($value) ? $value : FilePathDataType::join($this->getWorkbench()->filemanager()->getPathToVendorFolder(), $value);
        return $this;
    }
    
    protected function getDataPlaceholdersUxon() : ?UxonObject
    {
        return $this->placeholders;
    }
    
    /**
     * Additional data placeholders to be provided to the template
     * 
     * @uxon-property data_placeholders
     * @uxon-type \exface\Core\Templates\Placeholders\DataSheetPlaceholders[]
     * @uxon-template {"": {"row_template": "", "data_sheet": {"object_alias": "", "columns": [{"attribute_alias": ""}], "filters": {"operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}}}}
     * 
     * @param UxonObject $value
     * @return PrintTemplate
     */
    public function setDataPlaceholders(UxonObject $value) : PrintTemplate
    {
        $this->placeholders = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getTemplate() : string
    {
        return $this->template ?? file_get_contents($this->getTemplatePathAbsolute());
    }
    
    /**
     * The tempalte with placeholders to fill.
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
     * In addition to the general placeholders above, additional data can be loaded into the table:
     * e.g. positions of an order in addition to the actual order data, which is the input of the action.
     * 
     * Each entry in `data_placeholders` consists of a custom placeholder name (to be used in the main `template`) 
     * and a configuration for its contents:
     * 
     * - `data_sheet` to load the data 
     * - `row_template` to fill with placeholders from every row of the `data_sheet` - e.g. `[#~data:some_attribute#]`.
     * 
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
}