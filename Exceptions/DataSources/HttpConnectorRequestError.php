<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

/**
 * Error which can be thrown when an HTTP client returns a HTTP status code indicating
 * an error (4xx or 5xx).
 * 
 * @author SFL
 *
 */
class HttpConnectorRequestError extends DataConnectorError
{

    private $httpStatusCode = null;

    private $httpReasonPhrase = null;

    // Default Reason Phrases fuer verschiedene HTTP Statuscodes
    private $defaultReasonPhrase = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URL Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        420 => 'Policy Not Fulfilled',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        'unknown' => 'Unknown'
    ];

    // Default Fehler Aliase fuer verschiedene HTTP Statuscodes
    protected $defaultAlias = [
        0 => '6UY95WR',
        400 => '6UY4L3E',
        403 => '6UY5F5D',
        404 => '6V27IZ3',
        500 => '6UY86E7',
        'unknown' => '6UY88MN'
    ];

    /**
     * 
     * @param DataConnectionInterface $connector
     * @param int $httpStatusCode
     * @param string $httpReasonPhrase
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(DataConnectionInterface $connector, $httpStatusCode, $httpReasonPhrase = null, $message = null, $alias = null, $previous = null)
    {
        $this->setHttpStatusCode($httpStatusCode);
        $this->setHttpReasonPhrase($httpReasonPhrase);
        parent::__construct($connector, $message, (is_null($alias) || $alias == '') ? $this->getDefaultAlias() : $alias, $previous);
    }

    /**
     * Returns the HTTP status code. (e.g. 400)
     * 
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * Sets the HTTP status code. (e.g. 400)
     * 
     * @param int $httpStatusCode
     */
    public function setHttpStatusCode($httpStatusCode)
    {
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Returns the HTTP reason phrase. (e.g. 'Bad request')
     * 
     * @return string
     */
    public function getHttpReasonPhrase()
    {
        if (is_null($this->httpReasonPhrase) || $this->httpReasonPhrase == '') {
            $this->httpReasonPhrase = $this->getDefaultReasonPhrase($this->getHttpStatusCode());
        }
        return $this->httpReasonPhrase;
    }

    /**
     * Sets the HTTP reason phrase. (e.g. 'Bad request')
     * 
     * @param string $httpReasonPhrase
     */
    public function setHttpReasonPhrase($httpReasonPhrase)
    {
        $this->httpReasonPhrase = $httpReasonPhrase;
    }

    /**
     * Returns the default HTTP reason phrase for the passed HTTP status code.
     * (e.g. 'Bad Request' for 400)
     * 
     * @param int $httpStatusCode
     * @return string
     */
    protected function getDefaultReasonPhrase($httpStatusCode)
    {
        return array_key_exists($httpStatusCode, $this->defaultReasonPhrase) ? $this->defaultReasonPhrase[$httpStatusCode] : $this->defaultReasonPhrase['unknown'];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return array_key_exists($this->getHttpStatusCode(), $this->defaultAlias) ? $this->defaultAlias[$this->getHttpStatusCode()] : $this->defaultAlias['unknown'];
    }
}