<?php
namespace exface\Core\Formulas;


use exface\Core\DataTypes\UUIDDataType;

/**
 * Generates various types of unique ids - in particular the SQL optimized UID used for primary keys in the metamodel
 * 
 * E.g. `=UniqueId()` => 0x11edbb9aae282fdabb9a025041000001
 *
 * @author Andrej Kabachnik
 *        
 */
class UniqueId extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    function run(string $type = 'sql')
    {
        $params = [];
        for ($i = 1; $i < func_num_args(); $i ++) {
            $params[] = func_get_arg($i);
        }
        switch (strtolower($type)) {
            case 'v1':
                $uid = UUIDDataType::generateUuidV1();
                break;
            case 'v2':
                $uid = UUIDDataType::generateUuidV2($params[0] ?? 0);
                break;
            case 'v3':
                $uid = UUIDDataType::generateUuidV3($params[0], $params[1], $params[2] ?? '0x');
                break;
            case 'v4':
                $uid = UUIDDataType::generateUuidV4($params[0] ?? '0x');
                break;
            default: 
                $uid = UUIDDataType::generateSqlOptimizedUuid();
        }
        return $uid;
    }
}