<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\FormulaFactory;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;

/**
 * Resolves placeholders by evaluating them as formulas - e.g. `=Now()`.
 * 
 * @author Andrej Kabachnik
 */
class FormulaPlaceholders extends AbstractPlaceholderResolver
{
    use SanitizedPlaceholderTrait;
    
    private $workbench = null;
    
    private $dataSheet = null;
    
    private $rowNumber = null;
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(WorkbenchInterface $workbench, DataSheetInterface $dataSheet = null, $rowNo = null, string $prefix = '=')
    {
        $this->setPrefix($prefix);
        $this->workbench = $workbench;
        $this->dataSheet = $dataSheet;
        $this->rowNumber = $rowNo;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $placeholder = trim($placeholder);
            $exprString = $this->stripPrefix($placeholder);
            if (mb_substr($exprString, 0, 1) === '=') {
                continue;
            }
            $formula = FormulaFactory::createFromString($this->workbench, $exprString);
            $vals[$placeholder] = $this->sanitizeValue($formula->evaluate($this->dataSheet, $this->rowNumber));
        }
        return $vals;
    }
}