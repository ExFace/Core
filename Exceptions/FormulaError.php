<?php
namespace exface\Core\Exceptions;

use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Formulas\FormulaInterface;

/**
 * Exception thrown if a formula fails to calculate.
 *
 * TODO Add some formula-specific information here? The DebugMessage widget could then display the data sheet or something
 *
 * @author Andrej Kabachnik
 *        
 */
class FormulaError extends RuntimeException
{
    private FormulaInterface $formula;
    private ?array $arguments = null;

    /**
     * @param FormulaInterface $formula
     * @param $message
     * @param $alias
     * @param $previous
     * @param array|null $arguments
     */
    public function __construct(FormulaInterface $formula, $message, $alias = null, $previous = null, ?array $arguments = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->formula = $formula;
        $this->arguments = $arguments;
    }

    /**
     * {@inheritDoc}
     * @see ExceptionTrait::getLinks()
     */
    public function getLinks(): array
    {
        $links = parent::getLinks();
        $links['Formula syntax reference'] = DocsFacade::buildUrlToFile('/exface/Core/Docs/UXON/Formula_syntax.md');
        $links['Formula `=' . $this->formula->getFormulaName() . '()`'] = DocsFacade::buildUrlToDocsForUxonPrototype($this->formula);
        return $links;
    }
}