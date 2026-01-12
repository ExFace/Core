<?php
namespace exface\Core\Exceptions;

use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Formulas\FormulaInterface;

/**
 * Exception thrown if a formula class cannot be found
 *
 * @author Andrej Kabachnik
 *        
 */
class FormulaNotFoundError extends NotFoundError
{}