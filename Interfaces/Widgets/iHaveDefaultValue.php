<?php

namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Expression;

interface iHaveDefaultValue extends iTakeInput
{

    /**
     *
     * @return string
     */
    public function getDefaultValue();

    /**
     *
     * @return Expression
     */
    public function getDefaultValueExpression();

    /**
     *
     * @return boolean
     */
    public function getIgnoreDefaultValue();

    /**
     *
     * @param boolean $true_or_false            
     * @return iHaveDefaultValue
     */
    public function setIgnoreDefaultValue($true_or_false);
}