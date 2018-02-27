<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * HTTP response status codes: 200, 404, 500 etc.
 * 
 * @method HttpStatusCodeDataType HTTP_100(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_101(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_200(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_201(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_202(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_203(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_204(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_205(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_206(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_300(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_301(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_302(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_303(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_304(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_305(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_400(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_401(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_402(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_403(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_404(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_405(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_406(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_407(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_408(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_409(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_410(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_411(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_412(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_413(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_414(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_415(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_500(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_501(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_502(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_503(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_504(WorkbenchInterface $workbench)
 * @method HttpStatusCodeDataType HTTP_505(WorkbenchInterface $workbench)
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpStatusCodeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const HTTP_100 = 100;
    const HTTP_101 = 101;
    const HTTP_200 = 200;
    const HTTP_201 = 201;
    const HTTP_202 = 202;
    const HTTP_203 = 203;
    const HTTP_204 = 204;
    const HTTP_205 = 205;
    const HTTP_206 = 206;
    const HTTP_300 = 300;
    const HTTP_301 = 301;
    const HTTP_302 = 302;
    const HTTP_303 = 303;
    const HTTP_304 = 304;
    const HTTP_305 = 305;
    const HTTP_400 = 400;
    const HTTP_401 = 401;
    const HTTP_402 = 402;
    const HTTP_403 = 403;
    const HTTP_404 = 404;
    const HTTP_405 = 405;
    const HTTP_406 = 406;
    const HTTP_407 = 407;
    const HTTP_408 = 408;
    const HTTP_409 = 409;
    const HTTP_410 = 410;
    const HTTP_411 = 411;
    const HTTP_412 = 412;
    const HTTP_413 = 413;
    const HTTP_414 = 414;
    const HTTP_415 = 415;
    const HTTP_500 = 500;
    const HTTP_501 = 501;
    const HTTP_502 = 502;
    const HTTP_503 = 503;
    const HTTP_504 = 504;
    const HTTP_505 = 505;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        $labels = [];
        foreach ($this::getValuesStatic() as $val) {
            $labels[$val] = $this::getStatusMessage($val);
        }
        return $labels;
    }
    
    /**
     * Returns the text description for the given HTTP code: e.g. "OK" for 200.
     * 
     * @param int $code
     * @return string
     */
    public static function getStatusMessage($code)
    {
        switch ($code) {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 200: $text = 'OK'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default: $text = '';
        }
        
        return $text;
    }

}
?>