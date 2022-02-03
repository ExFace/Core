<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This action exports data as a JSON array of key-value-pairs.
 * 
 * By default, captions will be used for keys. Alternatively you can use attribute aliases by setting
 * `use_attribute_alias_as_header` = TRUE.
 * 
 * As all export actions do, this action will read all data matching the current filters (no pagination), eventually
 * splitting it into multiple requests. You can use `limit_rows_per_request` and `limit_time_per_request` to control this.
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::isDownloadable()
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
        $contents = $this->renderHtmlContents($inputData);
        foreach ($contents as $html) {
            file_put_contents($this->getFilePathAbsolute(), $html);
        }
        $result = ResultFactory::createFileResult($task, $this->getFilePathAbsolute());
        
        return $result;
    }
    
    /**
     * 
     * @param DataSheetInterface $inputData
     * @return array
     */
    protected function renderHtmlContents (DataSheetInterface $inputData) : array
    {
        $contents = [];
        $mainTpl = $this->getTemplate();
        $mainPhUxon = $this->getDataPlaceholdersUxon();
        
        $mainRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        // TODO add other placeholders
        
        $dataPhValues = [];
        foreach (array_keys($inputData->getRows()) as $rowNo) {
            $inputRowRenderer = $mainRenderer->copy();
            $inputRowRenderer->addPlaceholder(new DataRowPlaceholders($inputData, $rowNo, '~input:'));
            $inputRowRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench(),$inputData,$rowNo));
            
            $phRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
            $phRenderer->addPlaceholder((new DataRowPlaceholders($inputData, $rowNo, '~input:'))->setFormatValues(false));
            $phRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench(),$inputData,$rowNo));
            $phRenderer->setIgnoreUnknownPlaceholders(true);
            foreach ($mainPhUxon->getPropertiesAll() as $ph => $phTemplate) {
                $phUxon = UxonObject::fromJson($phRenderer->render($phTemplate->toJson()));
                
                $phData = DataSheetFactory::createFromUxon($this->getWorkbench(), $phUxon->getProperty('data_sheet'));
                $phData->dataRead();
                
                $phRowTpl = $phUxon->getProperty('row_template');
                $phRendered = '';
                foreach (array_keys($phData->getRows()) as $phDataRowNo) {
                    $phDataRenderer = $inputRowRenderer->copy();
                    $phDataRenderer->addPlaceholder(new DataRowPlaceholders($phData, $phDataRowNo, '~data:'));
                    $phRendered .= $phDataRenderer->render($phRowTpl);
                }
                $dataPhValues[$ph] = $phRendered;
            }
            
            $inputRowRenderer->setDefaultPlaceholderResolver(new ArrayPlaceholders($dataPhValues, ''));
            $mainTplRendered = $inputRowRenderer->render($mainTpl);
            $contents[] = $mainTplRendered;
        }
        return $contents;
    }

    /**
     * Set to FALSE to prevent direct downloading of the exported file (i.e. just export, no download).
     * 
     * @uxon-property downloadable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setDownloadable()
     */
    public function setDownloadable($true_or_false) : iExportData
    {
        $this->downloadable = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getFilename()
     */
    public function getFilename() : string
    {
        if ($this->filename === null){
            return 'print_' . date('Y-m-d_his', time()) . $this->getFileExtension();
        }
        return $this->filename;
    }
    
    protected function getFileExtension() : string
    {
        return '.html';
    }

    /**
     * Explicitly sets a fixed name for the export file.
     * 
     * If no file name is specified, it will be generated from the export time: e.g. `export_2018-10-22 162259`.
     * 
     * @uxon-property filename
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setFilename()
     */
    public function setFilename(string $filename) : iExportData
    {
        $this->filename = $filename;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getMimeType()
     */
    public function getMimeType() : ?string
    {
        if ($this->mimeType === null && get_class($this) === PrintTemplate::class) {
            return 'application/json';
        }
        return $this->mimeType;
    }
    
    /**
     * Explicitly specifies a mime type for the download.
     * 
     * @uxon-property mime_type
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setMimeType()
     */
    public function setMimeType(string $mimeType) : iExportData
    {
        $this->mimeType = $mimeType;
        return $this;
    }  
    
    /**
     * Returns the absolute path to the file.
     *
     * @return string
     */
    protected function getFilePathAbsolute() : string
    {
        if (is_null($this->pathname)) {
            $filemanager = $this->getWorkbench()->filemanager();
            $this->pathname = Filemanager::pathJoin([
                $filemanager->getPathToCacheFolder(),
                $this->getFilename()
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
     * Path to the template file - absolute or relative to the vendor folder
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
    
    protected function getDataPlaceholdersUxon() : UxonObject
    {
        return $this->placeholders;
    }
    
    /**
     * Additional data placeholders to be provided to the template
     * 
     * @uxon-property data_placeholders
     * @uxon-type object
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
    
    protected function getTemplate() : string
    {
        return $this->template ?? file_get_contents($this->getTemplatePathAbsolute());
    }
    
    /**
     * The tempalte to fill - as an alternative to `template_path`.
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