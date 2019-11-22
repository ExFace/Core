<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * 
 * @method EnumDataTypeInterface getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsEnumFormatter extends AbstractJsDataTypeFormatter
{
    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof EnumDataTypeInterface) {
            // TODO
        }
        return parent::setDataType($dataType);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        $js_value_labels = json_encode($this->getDataType()->getLabels());
        return <<<JS
    function() {
        var labels = {$js_value_labels};
        var key = {$jsInput};
        return labels[key] ? labels[key] : {$jsInput};
    }()
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        return $jsInput;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array
    {
        return [];
    }
}
