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
        $phData = $this->getDataSheet();
        $phData->dataRead();
        
        $phRowTpl = $this->getRowTemplate();
        $phRendered = '';
        foreach (array_keys($phData->getRows()) as $phDataRowNo) {
            $phDataRenderer = $this->rowRenderer->copy();
            $phDataRenderer->addPlaceholder(new DataRowPlaceholders($phData, $phDataRowNo, $this->prefix));
            $phRendered .= $phDataRenderer->render($phRowTpl);
        }
        
        return [$this->placeholder => $phRendered];;
    }
    
    protected function getDataSheet() : DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     * UXON model of the data to read with placholders
     * 
     * @uxon-property data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template 
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
    
    /**
     * 
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }
}