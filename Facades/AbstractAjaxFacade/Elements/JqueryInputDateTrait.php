<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\DataTypes\DateDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;
use exface\Core\Widgets\InputDate;
use exface\Core\Widgets\InputDateTime;
use exface\Core\Factories\DataTypeFactory;

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
        return $this->getDatatypeFormatter()->buildJsFormatter($jsInput);
    }
    
    /**
     * Returns the formatter class to generate javascript date formatting.
     * 
     * @return JsDateFormatter
     */
    protected function getDataTypeFormatter() {
        if (is_null($this->formatter)) {
            $widget = $this->getWidget();
            $type = $widget->getValueDataType();
            if (! $type instanceof DateDataType) {
                $type = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
            }
            /* @var $formatter \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter */
            $this->formatter = $this->getFacade()->getDataTypeFormatter($type);
            if ($format = $this->getWidget()->getFormat()) {
                $this->formatter->setFormat($format);
            } else {
                $this->formatter->setFormat($this->buildJsDateFormatDefault());
            }
        }
        
        return $this->formatter;
    }
    
    /**
     * Returns the format which is used to show dates on the screen.
     *
     * The format is specified in the translation files "DATE.FORMAT.SCREEN".
     *
     * @return string
     */
    protected function buildJsDateFormatDefault()
    {
        return $this->getWidget() instanceof InputDateTime ? $this->translate("DATETIME.FORMAT.SCREEN") : $this->translate("DATE.FORMAT.SCREEN");
    }    
}
