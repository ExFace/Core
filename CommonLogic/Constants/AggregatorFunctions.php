<?php
namespace exface\Core\CommonLogic\Constants;

use MyCLabs\Enum\Enum;

/**
 * 
 * @method AggregatorFunctions SUM()
 * @method AggregatorFunctions AVG()
 * @method AggregatorFunctions MIN()
 * @method AggregatorFunctions MAX()
 * @method AggregatorFunctions LIST()
 * @method AggregatorFunctions LIST_DISTINCT()
 * @method AggregatorFunctions COUNT()
 * @method AggregatorFunctions COUNT_DISTINCT()
 * @method AggregatorFunctions COUNT_IF()
 * 
 * @author Andrej Kabachnik
 *
 */
class AggregatorFunctions extends Enum
{
    const SUM = 'SUM';
    
    const AVG = 'AVG';
    
    const MIN = 'MIN';
    
    const MAX = 'MAX';
    
    const LIST = 'LIST';
    
    const LIST_DISTINCT = 'LIST_DISTINCT';
    
    const COUNT = 'COUNT';
    
    const COUNT_DISTINCT = 'COUNT_DISTINCT';
    
    const COUNT_IF = 'COUNT_IF';
}
