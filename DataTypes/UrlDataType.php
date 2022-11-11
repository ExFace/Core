<?php
namespace exface\Core\DataTypes;

use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Uri;

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
        return preg_match('@^[a-z1-2\+\-\._]+://@i', $url) === 1;
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
    
    /**
     * Returns the path of the given URL - e.g. `my/path` from `http://localhost/my/path`.
     * 
     * @param UriInterface|string $stringOrUri
     * @return string
     */
    public static function findPath($stringOrUri) : string
    {
        if ($stringOrUri instanceof UriInterface) {
            $uri = $stringOrUri;
        } else {
            $uri = new Uri($stringOrUri);
        }
        return $uri->getPath() ?? '';
    }
    
    /**
     * Returns the host of the given URI - e.g. `domain.com` from `https://domain.com/path`.
     * 
     * @param UriInterface|string $stringOrUri
     * @return string
     */
    public static function findHost($stringOrUri) : string
    {
        if ($stringOrUri instanceof UriInterface) {
            $uri = $stringOrUri;
        } else {
            $uri = new Uri($stringOrUri);
        }
        return $uri->getHost() ?? '';
    }
    
    /**
     * Removes all query parameters including the `?` from the given URI.
     * 
     * For example: http://domain.com/path?param1=1&param2=2 -> http://domain.com/path.
     * 
     * @param UriInterface|string $stringOrUri
     * @return string
     */
    public static function stripQuery($stringOrUri) : string
    {
        if ($stringOrUri instanceof UriInterface) {
            return $stringOrUri->withQuery('')->__toString();
        }
        return self::substringBefore($stringOrUri, '?', $stringOrUri);
    }
}