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
 * @method OfflineStrategyDataType CLIENT_SIDE(\exface\Core\CommonLogic\Workbench $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class OfflineStrategyDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    /**
     * Use the action queue and send the action to the server in background when connected again
     */
    const ENQUEUE = 'enqueue';
    
    /**
     * Constantly sync data in background using PWA data sets
     */
    const PRESYNC = 'presync';

    /**
     * If offline, try to use the service worker caches making previously loaded data available offline
     */ 
    const USE_CACHE = 'use_cache';

    /**
     * Skip the action offline
     */
    const SKIP = 'skip';

    /** 
     * Allow the action online only
     */
    const ONLINE_ONLY = 'online_only';

    /** 
     * Ignore the action completely - don't even save it to the PWA model!
     */
    const IGNORE = 'ignore';

    /** 
     * JS-only actions need no special treatment offline
     */
    const CLIENT_SIDE = 'client_side';
    
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
    
    /**
     * 
     * @param array $exceptions
     * @return array
     */
    public static function getStrategies(array $exceptions = []) : array
    {
        $all = array_keys(static::getValuesStatic());
        if (! empty($exceptions)) {
            return array_diff($all, $exceptions);
        }
        return $all;
    }
}