<?php
namespace exface\Core\DataTypes;

use Ramsey\Uuid\Uuid;
use Ramsey;
use Ramsey\Uuid\Type\Hexadecimal;

/**
 * Data type for various unique identifiers.
 * 
 * @author Andrej Kabachnik
 *
 */
class UUIDDataType extends HexadecimalNumberDataType
{
    /**
     * Generates a variable length UID based on PHP `uniqid("", true)`
     * 
     * @param int $length
     * @param string $salt
     * @param bool $upperCase
     * 
     * @return string
     */
    public static function generateShortId(int $length = 8, string $salt = '', bool $upperCase = false) : string
    {  
        $hex = md5($salt . uniqid("", true));
        
        $pack = pack('H*', $hex);
        $tmp =  base64_encode($pack);
        $tmp = $upperCase ? strtoupper($tmp) : $tmp;
        
        $uid = preg_replace("#(*UTF8)[^A-Za-z0-9]#", "", $tmp);
        
        $length = max(4, min(128, $length));
        
        while (strlen($uid) < $length) {
            $uid .= gen_uuid(22);
        }
            
        return substr($uid, 0, $length);
    }
    
    /**
     * Generates a short semi-unique id from the current time with microseconds.
     * 
     * Examples:
     * 
     * - 5PQ8JMU9Y  ($msPlaces = 4)
     * - KKMGD310   ($msPlaces = 3)
     * - 2228U3WI   ($msPlaces = 2)
     * - 7EMHESG    ($msPlaces = 1)
     * - 2NZGD2     ($msPlaces = -1)
     * 
     * @param int $msPlaces
     * 
     * @return string
     */
    public static function generateShortIdFromTime(int $msPlaces = 1) : string
    {
        return strtoupper(base_convert(round(microtime(true)*pow(10, $msPlaces)),10,36));
    }
    
    /**
     * Generates a reordered UUID v1 (time-based) optimized for use in SQL primary keys.
     * 
     * The returned values already has a `0x` prefix, so it can be used in SQL as-is.
     * 
     * @return string
     */
    public static function generateSqlOptimizedUuid()
    {
        $uuid = self::generateUuidV1('');
        $sqlUuid = '0x' . substr($uuid, 14, 4) . substr($uuid, 19, 4) . substr($uuid, 0, 8) . substr($uuid, 19, 4) . substr($uuid, 24);
        return $sqlUuid;
    }

    /**
     * Generates a version 1 (time-based) UUID from a host ID, sequence number, and the current time
     * 
     * @param string prefix
     * @param int|string|NULL $node A 48-bit number representing the
     *     hardware address; this number may be represented as an integer or a
     *     hexadecimal string
     * @param int|NULL $clockSeq A 14-bit number used to help avoid duplicates that
     *     could arise when the clock is set backwards in time or if the node ID
     *     changes
     *
     * @return string
     */
    public static function generateUuidV1(string $prefix = '0x', $node = null, ?int $clockSeq = null) : string
    {
        return $prefix . Uuid::uuid1($node, $clockSeq)->toString();
    }
    
    /**
     * Generateds a version 2 (DCE Security) UUID from a local domain, local
     * identifier, host ID, clock sequence, and the current time
     * 
     * @param string prefix
     * @param int $localDomain The local domain to use when generating bytes,
     *     according to DCE Security
     * @param int|null $localIdentifier The local identifier for the
     *     given domain; this may be a UID or GID on POSIX systems, if the local
     *     domain is person or group, or it may be a site-defined identifier
     *     if the local domain is org
     * @param string|null $node A 48-bit number representing the hardware
     *     address
     * @param int|null $clockSeq A 14-bit number used to help avoid duplicates
     *     that could arise when the clock is set backwards in time or if the
     *     node ID changes (in a version 2 UUID, the lower 8 bits of this number
     *     are replaced with the domain).
     *
     * @return string
     */
    public static function generateUuidV2(int $localDomain, string $prefix = '0x', ?int $localIdentifier = null, ?string $node = null, ?int $clockSeq = null) : string
    {
        $localIdentifier = $localIdentifier !== null ? new Ramsey\Uuid\Type\Integer($localIdentifier) : null;
        $node = $node !== null ? new Hexadecimal($localIdentifier) : null;
        return $prefix . Uuid::uuid2($localDomain, $localIdentifier, $node, $clockSeq)->toString();
    }
    
    /**
     * Generates a version 3 (name-based) UUID based on the MD5 hash of a namespace ID and a name
     *
     * @param string prefix
     * @param string $ns The namespace (must be a valid UUID)
     * @param string $name The name to use for creating a UUID
     * 
     * @return string
     */
    public static function generateUuidV3(string $ns, string $name, string $prefix = '0x') : string
    {
        return $prefix . Uuid::uuid3($ns, $name)->toString();
    }
    
    /**
     * Generates a version 4 (random) UUID
     * 
     * @param string prefix
     * 
     * @return string
     */
    public static function generateUuidV4(string $prefix = '0x') : string
    {
        return $prefix . Uuid::uuid4()->toString();
    }
}