<?php
namespace exface\Core\DataTypes;

class UrlDataType extends StringDataType
{
    private $baseUrl = null;
    
    /**
     * Advanced version of urlencode() that prevents parameter names to be altered by PHP.
     * 
     * @link http://php.net/variables.external#language.variables.external.dot-in-names
     * 
     * @param string $string
     * @return string
     */
    public static function urlEncode(string $string) : string
    {
        return str_replace('.', '%2E', urlencode($string));
    }
    
    /**
     * 
     * @param string $url
     * @return bool
     */
    public static function isAbsolute(string $url) : bool
    {
        return StringDataType::startsWith('http', $url, false);
    }
    
    /**
     *
     * @return string
     */
    public function getBaseUrl() : ?string
    {
        return $this->baseUrl;
    }
    
    /**
     * Adds a base to every URL.
     * 
     * Use this if your data only includes the last part of the URL. You can prefix
     * it then with an absolute or relative base. This will not change the value,
     * but will tell widgets and other components to use this base automatically.
     * 
     * @uxon-property base_url
     * @uxon-type uri
     * 
     * @param string $value
     * @return UrlDataType
     */
    public function setBaseUrl(string $value) : UrlDataType
    {
        $this->baseUrl = $value;
        return $this;
    }
}