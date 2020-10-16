<?php
namespace exface\Core\DataTypes;

use Ramsey\Uuid\Uuid;

/**
 * Data type for UUIDs.
 * 
 * @author Andrej Kabachnik
 *
 */
class UUIDDataType extends HexadecimalNumberDataType
{
    public static function generateSqlOptimizedUuid()
    {
        $uuid = self::generateUuidV1();
        $sqlUuid = '0x' . substr($uuid, 14, 4) . substr($uuid, 19, 4) . substr($uuid, 0, 8) . substr($uuid, 19, 4) . substr($uuid, 24);
        return $sqlUuid;
    }

    /**
     * Generates valide RFC 4211 compilant Universally Unique IDentifier (UUID) version 1
     *
     * @return string
     */
    public static function generateUuidV1() : string
    {
        return Uuid::uuid1()->toString();
    }
    
    /**
     * Generates valide RFC 4211 compilant Universally Unique IDentifier (UUID) version 2
     *
     * @return string
     */
    public static function generateUuidV2() : string
    {
        return Uuid::uuid2()->toString();
    }
    
    /**
     * Generates valide RFC 4211 compilant Universally Unique IDentifier (UUID) version 3
     *
     * @return string
     */
    public static function generateUuidV3() : string
    {
        return Uuid::uuid3()->toString();
    }
    
    /**
     * Generates valide RFC 4211 compilant Universally Unique IDentifier (UUID) version 4
     * 
     * @return string
     */
    public static function generateUuidV4() : string
    {
        return Uuid::uuid4()->toString();
        /*return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );*/
    }
}