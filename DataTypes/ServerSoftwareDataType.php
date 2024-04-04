<?php
namespace exface\Core\DataTypes;

/**
 * Data type for server software names like `Apache/2.4.46`
 * 
 * Includes static helper methods to identify the current server environment
 * 
 * @author Andrej Kabachnik
 *
 */
class ServerSoftwareDataType extends StringDataType
{
    /**
     * Returns the full name of the servers software, that runs the workbench
     * 
     * Examples:
     * 
     * - Apache/2.4.46 (Win64) PHP/8.0.14 mod_fcgid/2.3.10-dev
     * - Microsoft-IIS/10.0
     * - nginx/1.24.0
     * - PHP/8.3.0 (Development Server) 
     * 
     * @return string|NULL
     */
    public static function getServerSoftware() : ?string
    {
        return $_SERVER['SERVER_SOFTWARE'] ?? null;
    }
    
    /**
     * Returns family/type of the server software
     * 
     * - Apache
     * - Microsoft-IIS
     * - nginx
     * - PHP 
     * 
     * @return string|NULL
     */
    public static function getServerSoftwareFamily() : ?string
    {
        $software = static::getServerSoftware();
        return StringDataType::substringBefore($software ?? '', '/', $software);
    }
    
    /**
     * Returns the version of the server softwareExamples:
     * 
     * - 2.4.46
     * - 10.0
     * - 1.24.0
     * - 8.3.0
     * 
     * @return string|NULL
     */
    public static function getServerSoftwareVersion() : ?string
    {
        $parts = explode('/', static::getServerSoftware() ?? '');
        switch (count($parts)) {
            case 0:
            case 1:
                $version = null;
                break;
            default:
                $version = StringDataType::substringBefore($parts[1], ' ', $parts[1]);
                break;
        }
        return $version;
    }
    
    /**
     *
     * @return bool
     */
    public static function isServerIIS() : bool
    {
        return strcasecmp(static::getServerSoftwareFamily(), 'Microsoft-IIS') === 0;
    }
    
    /**
     *
     * @return bool
     */
    public static function isServerApache() : bool
    {
        return strcasecmp(static::getServerSoftwareFamily(), 'Apache') === 0;
    }
    
    /**
     *
     * @return bool
     */
    public static function isServerNginx() : bool
    {
        return strcasecmp(static::getServerSoftwareFamily(), 'nginx') === 0;
    }
    
    /**
     * 
     * @return bool
     */
    public static function isOsWindows() : bool
    {
        return substr(php_uname(), 0, 7) === "Windows";
    }
    
    /**
     * 
     * @return bool
     */
    public static function isOsLinux() : bool
    {
        return ! static::isOsWindows();
    }
    
    /**
     * Check if php script is run in a CLI environment
     *
     * @return boolean
     */
    public static function isCLI()
    {
        if ( defined('STDIN') )
        {
            return true;
        }
        
        if ( php_sapi_name() === 'cli' )
        {
            return true;
        }
        
        if ( array_key_exists('SHELL', $_ENV) ) {
            return true;
        }
        
        if ( empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0)
        {
            return true;
        }
        
        if ( !array_key_exists('REQUEST_METHOD', $_SERVER) )
        {
            return true;
        }
        
        return false;
    }
}