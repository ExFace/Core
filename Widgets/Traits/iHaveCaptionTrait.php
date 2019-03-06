<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;

/**
 * This trait adds the caption property to a widget or a widget part.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveCaptionTrait {
    
    private $hide_caption = false;
    
    private $caption = null;
    
    /**
     * Sets the caption or title of the widget.
     *
     * @uxon-property caption
     * @uxon-type string|metamodel:formula
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::setCaption()
     */
    public function setCaption($caption)
    {
        $this->caption = $this->evaluatePropertyExpression($caption);
        return $this;
    }
    
    /**
     * Evaluates the formula in the passed $value and returns the result.
     *
     * This can be used to translate certain attributes, e.g. the caption:
     * =TRANSLATE('exface.Core', 'TRANSLATION.KEY', '%placeholder1%=>value1|%placeholder2%=>value2', '1')
     * =TRANSLATE('exface.Core', 'ACTION.CREATEDATA.RESULT', '%number%=>Zwei', '2')
     * =TRANSLATE('exface.Core', 'ACTION.CREATEDATA.NAME')
     *
     * Only static formulas are evaluated, otherwise the passed $value is returned.
     *
     * @param string $string
     * @return string
     */
    protected function evaluatePropertyExpression(string $string) : string
    {
        if (Expression::detectFormula($string)) {
            $expr = ExpressionFactory::createFromString($this->getWorkbench(), $string);
            if ($expr->isStatic()) {
                return $expr->evaluate();
            }
        }
        return $string;
    }
    
    
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveCaption::getCaption()
     */
    function getCaption()
    {
        return $this->caption;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveCaption::getHideCaption()
     */
    public function getHideCaption()
    {
        return $this->hide_caption;
    }
    
    /**
     * Set to TRUE to hide the caption of the widget.
     *
     * @uxon-property hide_caption
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritdoc}
     *
     * @see iHaveCaption::setHideCaption()
     */
    public function setHideCaption($value)
    {
        $this->hide_caption = $value;
        return $this;
    }
}