<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration for offline strategies (e.g. for actions): normal, promoted, hidden and optional.
 * 
 * @method OfflineStrategyDataType ENQUEUE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method OfflineStrategyDataType PRESYNC(\exface\Core\CommonLogic\Workbench $workbench)
 * @method OfflineStrategyDataType USE_CACHE(\exface\Core\CommonLogic\Workbench $workbench)
 * @method OfflineStrategyDataType SKIP(\exface\Core\CommonLogic\Workbench $workbench)
 * @method OfflineStrategyDataType ONLINE_ONLY(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class OfflineStrategyDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const ENQUEUE = 'enqueue';
    
    const PRESYNC = 'presync';
    
    const USE_CACHE = 'use_cache';
    
    const SKIP = 'skip';
    
    const ONLINE_ONLY = 'online_only';
    
    private $labels = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if (empty($this->labels)) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            
            foreach (OfflineStrategyDataType::getValuesStatic() as $val) {
                $this->labels[$val] = $translator->translate('OFFLINE.STRATEGY.' . mb_strtoupper($val));
            }
        }
        
        return $this->labels;
    }
}