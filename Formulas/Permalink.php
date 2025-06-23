<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Facades\PermalinkFacade;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Produces a persistent, absolute permalink URL from a given permalink config alias and argument string.
 * 
 * ## Parameters
 * 
 * `=Permalink(string configAlias, string|array arguments)`
 * 
 * - string **configAlias**: The alias (with namespace) of the permalink config that will be used to redirect
 * the resulting link. This object contains information about the type of permalink and how to access the
 * destination. Make sure a matching config exists and is suitably configured for your use-case.
 * - string|array **arguments**: Provide a list of arguments for the permalink. What arguments are required and in what
 * order depends on the permalink type found the config referenced with `configAlias`. 
 * 
 * ## Examples: 
 * 
 * - `=Permalink('exface.core.show_object', '8978')` => https://myserver.com/api/permalink/exface.core.show_object/8978
 * - `=Permalink('exface.core.run_flow', ['8978','arg1','arg2'])` => https://myserver.com/api/permalink/exface.core.run_flow/8978/arg1/arg2
 * 
 */
class Permalink extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see Formula::run
     */
    public function run(string $configAlias = '', string|array $args = [])
    {
        if(empty($configAlias)) {
            throw new FormulaError('Cannot evaluate Permalink formula: no valid config provided!');
        }
        
        $args = is_array($args) ? implode('/', $args) : $args;
        
        return PermalinkFacade::buildAbsolutePermalinkUrl($this->getWorkbench(), $configAlias, $args);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see Formula::getDataType
     */
    public function getDataType() : DataTypeInterface
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), UrlDataType::class);
    }
}