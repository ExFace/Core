<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;

class BooleanDataType extends AbstractDataType
{
    /**
     * 
     * {@inheritdoc}
     * @see AbstractDataType::format()
     */
    public static function cast($string)
    {
        if ($string === null || $string === '' || strcasecmp($string, EXF_LOGICAL_NULL) === 0){
            return null;
        }
        return filter_var($string, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::format()
     */
    public function format($value = null) : string
    {
        $val = $this->parse($value);
        if ($val === null || $val === EXF_LOGICAL_NULL) {
            return '';
        }
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return $val ? $translator->translate('WIDGET.SELECT_YES') : $translator->translate('WIDGET.SELECT_NO');
    }
}