<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\FormulaFactory;

/**
 * Resolves placeholders by evaluating them as formulas - e.g. `=Now()`.
 * 
 * @author Andrej Kabachnik
 */
class FormulaPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    private $prefix = null;
    
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
        $this->prefix = $prefix;
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
        foreach ($this->filterPlaceholders($placeholders, $this->prefix) as $placeholder) {
            $placeholder = trim($placeholder);
            if (mb_substr($placeholder, 0, 1) === '==' || mb_substr($placeholder, -1) !== ')') {
                continue;
            }
            $formula = FormulaFactory::createFromString($this->workbench, $placeholder);
            $vals[$placeholder] = $formula->evaluate($this->dataSheet, $this->rowNumber);
        }
        return $vals;
    }
}