<?php
namespace exface\Core\DataTypes;

use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Formulas\SubstringAfter;

/**
 * 
 * @author Thomas Ressel
 * 
 */
class IPDataType extends TextDataType 
{
    private $type = null;
    
    const IPV4 = "IPv4";
    const IPV6 = "IPv6";

    /**
     * Validates IPv4 and IPv6
     * @param string $ip
     * @return string
     */
    public static function castIP(string $ip) : string
    {
        if (false !== $IPAddress = filter_var($ip, FILTER_VALIDATE_IP)) {
            return $IPAddress;
        } else {
            throw new DataTypeCastingError("Value '{$ip}' cannot be casted to IpDataType.");
        }
    }

    /**
     * Validates IPv4
     * @param string $ip
     * @return string
     */
    public static function castIPv4(string $ip) : string
    {
        if (false !== $IPAddress = filter_var($ip, FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) {
            return $IPAddress;
        } else {
            throw new DataTypeCastingError("Value '{$ip}' cannot be casted to IpDataType.");
        }
    }

    /**
     * Validates IPv6
     * @param string $ip
     * @return string
     */
    public static function castIPv6(string $ip) : string
    {
        if (false !== $IPAddress = filter_var($ip, FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)) {
            return $IPAddress;
        } else {
            throw new DataTypeCastingError("Value '{$ip}' cannot be casted to IpDataType.");
        }
    }
    
    /**
     * Private ranges: Non-internet facing IP addresses used in an internal network
     * @param string $ip
     * @return bool
     */
    public static function isIPPrivate(string $ip) : bool
    {
        if (false === filter_var($ip, FILTER_FLAG_NO_PRIV_RANGE)) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Reserved ranges: https://en.wikipedia.org/wiki/Reserved_IP_addresses
     * @param string $ip
     * @return bool
     */
    public static function isIPReserved(string $ip) : bool
    {
        if (false === filter_var($ip, FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     *
     * @param string $ip
     * @return bool
     */
    public static function isIPv4(string $ip) : bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) !== false) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     *
     * @param string $ip
     * @return bool
     */
    public static function isIPv6(string $ip) : bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP,FILTER_FLAG_IPV6) !== false) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Checks if the given IP matches a range
     * 
     * @param string $ip
     * @param string $range
     * @return bool
     */
    public static function isIPInRange(string $ip, string $range) : bool
    {
        $type = static::findIPtype($ip);
        switch (true) {
            case $type === self::IPV4: return static::isIPv4InRange($ip, $range);
            case $type === self::IPV6: return static::isIPv6InRange($ip, $range);
        }
        return false;
    }

    /**
     * Checks if the given IP v4 matches a range
     * 
     * Range formats:
     * 
     * 1. Wildcard:  Class A (10.*.*.*), Class B (180.16.*.*) or Class C (192.137.15.*)
     * 2. CIDR:      1.2.3/23  OR  1.2.3.4/255.255.255.0
     * 3. Start-End: 1.2.3.0-1.2.3.255
     * 
     * @param string $ip
     * @param string $range
     * @return bool
     */
    public static function isIPv4InRange(string $ip, string $range) : bool 
    {
        if ($ip === $range) {
            return true;
        }
        
        if (strpos($range, '/') !== false) {
            // IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);
            if (strpos($netmask, '.') !== false) {
                // 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmaskDec = ip2long($netmask);
                return ( (ip2long($ip) & $netmaskDec) == (ip2long($range) & $netmaskDec) );
            } else {
                // CIDR
                $x = explode('.', $range);
                while(count($x)<4) $x[] = '0';
                list($a,$b,$c,$d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a)?'0':$a, empty($b)?'0':$b,empty($c)?'0':$c,empty($d)?'0':$d);
                $rangeDec = ip2long($range);
                $ipDec = ip2long($ip);
                $wildcardDec = pow(2, (32-$netmask)) - 1;
                $netmaskDec = ~ $wildcardDec;
                return (($ipDec & $netmaskDec) == ($rangeDec & $netmaskDec));
            }
        } else {
            // Wildcard or Start-End format
            if (strpos($range, '*') !==false) {
                // Convert Wildcard to Start-End format
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }
            
            // Start-End format
            if (strpos($range, '-')!==false) {
                list($lower, $upper) = explode('-', $range, 2);
                $lowerDec = (float)sprintf("%u",ip2long($lower));
                $upperDec = (float)sprintf("%u",ip2long($upper));
                $ipDec = (float)sprintf("%u",ip2long($ip));
                return ( ($ipDec>=$lowerDec) && ($ipDec<=$upperDec) );
            }
            return false;
        }
    }

    /**
     * Checks if the given IP v6 matches a range
     * 
     * Range format: CIDR: 2001:800::/21 OR 2001::/16
     * 
     * @param string $ip
     * @param string $range
     * @return bool
     */
    public static function isIPv6InRange(string $ip, string $range) : bool
    {
        $ip = self::convertIPv6ToDecimal($ip);
        $parts = explode ("/", $range, 2);
        $leftPart = $parts[0];
        
        // Extract the main IP pieces
        $ipParts = explode("::", $leftPart, 2);
        $mainIpPart = $ipParts[0];
        $lastIpPart = $ipParts[1];
        
        // Pad out the shorthand entries.
        $mainIpParts = explode(":", $mainIpPart);
        foreach(array_keys($mainIpParts) as $key) {
            $mainIpParts[$key] = str_pad($mainIpParts[$key], 4, "0", STR_PAD_LEFT);
        }
        
        // Create the first and last pieces that will denote the IPv6 range.
        $first = $mainIpParts;
        $last = $mainIpParts;
        
        // Check to see if the last IP block (part after ::) is set
        $lastPart = "";
        $size = count($mainIpParts);
        if (trim($lastIpPart) != "") {
            $lastPart = str_pad($lastIpPart, 4, "0", STR_PAD_LEFT);
            
            // Build the full form of the IPv6 address if the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }
            $mainIpParts[7] = $lastPart;
        }
        else {
            // Build the full form of the IPv6 address
            for ($i = $size; $i < 8; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }
        }
        
        // Rebuild the final long form IPv6 address
        $first = self::ip2long6(implode(":", $first));
        $last = self::ip2long6(implode(":", $last));
        return ($ip >= $first && $ip <= $last);
    }
    
    /**
     * Used in method isIPv6InRange
     * @param string $ip
     * @return int
     */
    protected static function convertIPv6ToDecimal(string $ip) : int
    {
        $parts = explode ("/", $ip, 2);
        $leftPart = $parts[0];
        
        // Extract out the main IP parts
        $ipParts = explode("::", $leftPart, 2);
        $mainIpPart = $ipParts[0];
        $lastIpPart = $ipParts[1];
        
        // Pad out the shorthand entries.
        $mainIpParts = explode(":", $mainIpPart);
        foreach(array_keys($mainIpParts) as $key) {
            $mainIpParts[$key] = str_pad($mainIpParts[$key], 4, "0", STR_PAD_LEFT);
        }
        
        // Check to see if the last IP block (part after ::) is set
        $lastPart = "";
        $size = count($mainIpParts);
        if (trim($lastIpPart) != "") {
            $lastPart = str_pad($lastIpPart, 4, "0", STR_PAD_LEFT);
            
            // Build the full form of the IPv6 address if the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $mainIpParts[$i] = "0000";
            }
            $mainIpParts[7] = $lastPart;
        }
        else {
            // Build the full form of the IPv6 address
            for ($i = $size; $i < 8; $i++) {
                $mainIpParts[$i] = "0000";
            }
        }
        
        // Rebuild the final long form IPv6 address
        $finalIp = implode(":", $mainIpParts);
        return self::ip2long6($finalIp);
    }
    
    /**
     * Used in methods isIPv6InRange & convertIPv6ToDecimal
     * @param string $ip
     * @return int
     */ 
    protected static function ip2long6(string $ip) : int
    {
        if (substr_count($ip, '::')) {
            $ip = str_replace('::', str_repeat(':0000', 8 - substr_count($ip, ':')) . ':', $ip);
        }
        $ip = explode(':', $ip);
        $returnIp = '';
        foreach ($ip as $v) {
            $returnIp .= str_pad(base_convert($v, 16, 2), 16, 0, STR_PAD_LEFT);
        }
        return (int) base_convert($returnIp, 2, 10);
    }

    /**
     * Beware: All information starting with $_SERVER["HTTP_"] are headers from the client and can therefore be forged!
     * 
     * Due to the three way handshake of TCP/IP $_SERVER['REMOTE_ADDR'] cannot be spoofed. But it is impossible
     * to grab someone's IP address via PHP if their intent is to hide their IP address (see 5 proxy variants below).
     * 
     * To be read as general guidelines but NOT as strict rules without exceptions:
     * 
     * 1. No proxy server is used:
     * Remote_addr = your IP address
     * Http_via = no value or no display
     * Http_x_forwarded_for = no value or no display
     * 
     * 2. Transparent proxy server: transparent proxies
     * Remote_addr = IP address of the last Proxy Server
     * Http_via = Proxy Server IP Address
     * Http_x_forwarded_for = your real IP address. When multiple proxy servers are used,
     * this value is similar to the following: 203.98.1820.3, 203.98.1820.3, 203.129.72.215.
     * 
     * 3. Normal anonymous proxy server: anonymous proxies
     * Remote_addr = IP address of the last Proxy Server
     * Http_via = Proxy Server IP Address
     * Http_x_forwarded_for = Proxy Server IP address. When multiple proxy servers are used,
     * this value is similar to the following: 203.98.1820.3, 203.98.1820.3, 203.129.72.215.
     * 
     * 4. destorting proxies
     * Remote_addr = Proxy Server IP Address
     * Http_via = Proxy Server IP Address
     * Http_x_forwarded_for = random IP address. When multiple proxy servers are used,
     * the value is as follows: 203.98.182.163, 203.98.182.163, 203.129.72.215.
     *  
     * 5. High anonymity proxies (elite proxies)
     * Remote_addr = Proxy Server IP Address
     * Http_via = no value or no display
     * Http_x_forwarded_for = no value or no value is displayed. When multiple proxy servers are used,
     * the value is similar to the following: 203.98.182.163, 203.98.182.163, 203.129.72.215.
     * 
     * @link: https://topic.alibabacloud.com/a/remote_addr-http_client_ip-http_x_forwarded_for_8_8_32136183.html
     * 
     * @param ServerRequestInterface $request
     * @param array $proxyIPs
     * @return string|NULL
     */
    public static function findIPAddress(ServerRequestInterface $request, array $proxyIPs = []) : ?string
    {
        $server = $request->getServerParams();
        
        // Nothing to do without any reliable information.
        if (empty($server['REMOTE_ADDR'])) {
            return null;
        }

        // Get IP of the client behind trusted proxy:
        // If a known proxy is used, the REMOTE_ADDR contains the IP of the Proxy Server
        // and can be matched with allowed proxies in $proxyIPs.
        // The 'HTTP_X_FORWARDED_FOR' header information contains the real IP address if
        // a transparent proxy server is being used. Otherwise it may contain the
        // Proxy Server IP Address or even a random IP address.
        if (in_array($server['REMOTE_ADDR'], $proxyIPs)) {
            
            if (array_key_exists("HTTP_X_FORWARDED_FOR", $server)) {
                
                // Header can contain multiple IPs of proxies that are passed through.
                // Only the IP address added by the last proxy (last IP in the list) can be trusted.
                $clientIP = trim(end(explode(",", $server["HTTP_X_FORWARDED_FOR"])));
                $clientIP = static::stripPort($clientIP);
                
                // Validating the IP address is important in the last step since
                // the HTTP headers can be set to any arbitrary value.
                if (self::castIP($clientIP)) {
                    return $clientIP;
                } else {
                    return null;
                }
            }
        }
        // In case no proxy is used, REMOTE_ADDR is the only IP address we can trust.
        return $server['REMOTE_ADDR'];
    }

    /**
     * The type of the IP Address
     * @uxon-property type
     * @uxon-type [IPv4, IPv6]
     * @param string $type
     * @throws DataTypeConfigurationError
     * @return IpDataType
     */
    public function setType(string $type) : IpDataType
    {
        switch (strtoupper($type)) {
            
            case strtoupper(self::IPV4):
                $this->type = self::IPV4;
                break;
                
            case strtoupper(self::IPV6):
                $this->type = self::IPV6;
                break;
                
            default:
                throw new DataTypeConfigurationError($this, "The input '{$type}' is a invalid configuration value for this data type.");
        }
    }
    
    /**
     *
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }
    
    /**
     * 
     * @param string $ip
     * @return string|NULL
     */
    public static function findIPtype(string $ip) : ?string
    {
        if (self::isIPv4($ip) !== false) {
            return self::IPV4;
        }
        if (self::isIPv6($ip) !== false) {
            return self::IPV6;
        }
        return null;
    }

    /**
     * 
     * @param $string
     * @throws DataTypeCastingError
     * @return string
     */
    public static function cast($string) : string
    {
        if (static::isValueEmpty($string)) {
            return $string;
        }
        return static::castIP($string);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($string) : string
    {
        $ip = $this::cast($string);
        if (null !== $type = $this->getType()) {
            if($type === self::IPV4) {
                if ($this::isIPv4($ip) !== false) {
                    return $ip;
                }
            }
            if ($type === self::IPV6) {
                if ($this::isIPv6($ip) !== false) {
                    return $ip;
                }
            }
        }
        throw new DataTypeValidationError($this, "Value '{$ip}' is not a valid input for IpDataType.");
    }
    
    /**
     * 
     * @param string $ip4or6
     * @return string
     */
    public static function findPort(string $ip4or6) : string
    {
        return static::substringAfter($ip4or6, ':', $ip4or6, false, true);
    }
    
    /**
     *
     * @param string $ip4or6
     * @return string
     */
    public static function stripPort(string $ip4or6) : string
    {
        return static::substringBefore($ip4or6, ':', $ip4or6, false, true);
    }
}