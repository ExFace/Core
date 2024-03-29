<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\DataTypes\DateDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\DataTypes\TimestampDataType;

/**
 *
 * @method InputDate getWidget()
 * @method Workbench getWorkbench()
 * 
 * @author SFL
 *        
 */
trait JqueryInputDateTrait {

    private $formatter = null;
    
    /**
     * 
     * @param string $jsInput
     * @return string
     */
    protected function buildJsValueFormatter($jsInput)
    {
        return $this->getDateFormatter()->buildJsFormatter($jsInput);
    }
    
    /**
     * Returns the formatter class to generate javascript date formatting.
     * 
     * @return JsDateFormatter
     */
    protected function getDateFormatter() {
        if (is_null($this->formatter)) {
            $widget = $this->getWidget();
            $type = $widget->getValueDataType();
            // Date inputs will only work with dates, so if we don't have a date data type, 
            // we just create a new one for the formatter.
            if (! $type instanceof DateDataType && ! $type instanceof TimeDataType && ! $type instanceof TimestampDataType) {
                $type = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
            }
            /* @var $formatter \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter */
            $this->formatter = $this->getFacade()->getDataTypeFormatter($type);
            if ($format = $this->getWidget()->getFormat()) {
                $this->formatter->setFormat($format);
            }
        }
        
        return $this->formatter;
    }   
    
    protected function buildJsCallFunctionAddSubtract(array $parameters = []) : string
    {
        return <<<JS
(function(sInterval){
    var sVal = {$this->buildJsValueGetter()};
    var oDate = {$this->getDateFormatter()->buildJsFormatParserToJsDate('sVal')};
    if (oDate === null || oDate === undefined) {
        return null;
    }
    oDate = exfTools.date.add(oDate, sInterval);
    {$this->buildJsValueSetter($this->getDateFormatter()->buildJsFormatDateObjectToInternal('oDate'))};
})('{$parameters[0]}');
     
JS;
    }
}
