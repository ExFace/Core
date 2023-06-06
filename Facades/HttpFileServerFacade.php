<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\DataTypes\FilePathDataType;
use Intervention\Image\ImageManager;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\DataTypes\ComparatorDataType;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Behaviors\FileBehavior;
use exface\Core\Facades\AbstractHttpFacade\Middleware\OneTimeLinkMiddleware;
use Psr\SimpleCache\CacheInterface;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\Exceptions\FileNotFoundError;

/**
 * Facade to upload and download files using virtual pathes.
 * 
 * ## Download
 * 
 * Use the follosing url `api/files/my.App.OBJECT_ALIAS/uid` to download a file with the given `uid` value.
 * 
 * ### Image resizing
 * 
 * You can resize images by adding the URL parameter `&resize=WIDTHxHEIGHT`.
 * 
 * ### Encoding of UIDs
 * 
 * UID values MUST be properly encoded:
 * 
 * - URL encoded - unless they contain slashes (as many servers incl. Apache do not allow URL encoded slashes for security reasons)
 * - Base64 encoded with prefix `base64,` AND URL encoded on top - this is the most secure way to pass the UID value, but is
 * not readable at all.
 * 
 * ## Upload
 * 
 * Not available yet
 * 
 * ## Access restriction
 * 
 * This facade can be accessed by any authenticated (logged in) user by default. Please modify authorization policies if required!
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpFileServerFacade extends AbstractHttpFacade
{    
    private $otlUrlPathPart = 'otl';
    
    private $otlCacheName = '_onetimelink';

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/files';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        $uri = $request->getUri();
        $path = ltrim(StringDataType::substringAfter($uri->getPath(), $this->getUrlRouteDefault()), "/");
        
        $pathParts = explode('/', $path);
        $objSel = urldecode($pathParts[0]);
        $uid = urldecode($pathParts[1]);
        // See if there are additional parameters
        $params = [];
        parse_str($uri->getQuery() ?? '', $params);
        
        return $this->createResponseFromObjectUid($objSel, $uid, $params, $request);        
    }
    
    protected function createResponseFromObjectUid(string $objSel, string $uid, array $params, ServerRequestInterface $originalRequest = null) : ResponseInterface
    {
        if (StringDataType::startsWith($uid, 'base64,')) {
            $uid = base64_decode(substr($uid, 7));
        }
        $headers = $this->buildHeadersCommon();
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objSel);
        if (! $ds->getMetaObject()->hasUidAttribute()) {
            $e = new FacadeRuntimeError('Cannot serve file from object ' . $ds->getMetaObject()->__toString() . ': object has no UID attribute!');
            $this->getWorkbench()->getLogger()->logException($e);
            return $this->createResponseFromError($e, $originalRequest);
        }
        
        $colFilename = null;
        $colMime = null;
        $colContents = null;
        $attr = $this->findAttributeForContents($ds->getMetaObject());
        if ($attr) {
            $colContents = $ds->getColumns()->addFromAttribute($attr);
        } else {
            $e = new FacadeRuntimeError('Cannot find file contents attribute for object ' . $ds->getMetaObject()->__toString());
            $this->getWorkbench()->getLogger()->logException($e);
            return $this->createResponseFromError($e, $originalRequest);
        }
        $attr = $this->findAttributeForMimeType($ds->getMetaObject());
        if ($attr) {
            $colMime = $ds->getColumns()->addFromAttribute($attr);
        }
        $attr = $this->findAttributeForFilename($ds->getMetaObject());
        if ($attr) {
            $colFilename = $ds->getColumns()->addFromAttribute($attr);
        }
        
        $ds->getFilters()->addConditionFromAttribute($ds->getMetaObject()->getUidAttribute(), $uid, ComparatorDataType::EQUALS);
        $ds->dataRead();
        
        if ($ds->isEmpty()) {
            $e = new FileNotFoundError('Cannot find ' . $ds->getMetaObject()->__toString() . ' "' . $uid . '"');
            return $this->createResponseFromError($e, $originalRequest);
        }
        
        $contentType = $colContents->getDataType();
        $binary = null;
        $plain = null;
        $headers = array_merge($headers, [
            'Expires' => 0,
            'Cache-Control', 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public'
        ]);
        switch (true) {
            case $contentType instanceof BinaryDataType:
                $binary = $colContents->getDataType()->convertToBinary($colContents->getValue(0));
                $headers['Content-Transfer-Encoding'] = 'binary';
                break;
            default:
                $plain = $colContents->getValue(0);
                break;
        }
        
        // Create a response
        if ($colMime !== null) {
            $headers['Content-Type'] = $colMime->getValue(0);
        }
        if ($colFilename !== null) {
            $headers['Content-Disposition'] = 'attachment; filename=' . $colFilename->getValue(0);
        }
        
        // Resize images
        if ($binary !== null && null !== $resize = $params['resize'] ?? null) {
            list($width, $height) = explode('x', $resize);
            try {
                $newBinary = $this->resizeImage($binary, $width, $height);
                $binary = $newBinary;
            } catch (\Throwable $e) {
                if ($colFilename !== null) {
                    $text = $colFilename->getValue(0);
                    $text = strtoupper(FilePathDataType::findExtension($text));
                } else {
                    $text = 'FILE';
                }                
                $headers['Content-Type'] = 'image/jpeg';
                $headers['Content-Disposition'] = 'attachment; filename=placeholder.jpg';
                $binary = $this->createPlaceholderImage($text, $width, $height);
            }
        }
                        
        return new Response(200, $headers, stream_for($binary ?? $plain));        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getMiddleware()
     */
    protected function getMiddleware() : array
    {
        $middleware = parent::getMiddleware();
        $middleware[] = new OneTimeLinkMiddleware($this, $this->getOtlUrlPathPart());
        
        $allowBasicAuth = $this->getWorkbench()->getConfig()->getOption('FACADES.HTTPFILESERVERFACADE.ALLOW_HTTP_BASIC_AUTH');
        if ($allowBasicAuth === true) {
            $middleware[] = new AuthenticationMiddleware(
                $this,
                [
                    [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
                ]
            );
        }
        
        return $middleware;
    }
    
    /**
     *
     * @deprecated use buildUrlToDownloadFile()
     */
    public static function buildUrlForDownload(WorkbenchInterface $workbench, string $absolutePath, bool $relativeToSiteRoot = true)
    {
        return static::buildUrlToDownloadFile($workbench, $absolutePath, $relativeToSiteRoot);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $absolutePath
     * @param bool $relativeToSiteRoot
     * @throws FacadeRuntimeError
     * @return string
     */
    public static function buildUrlToDownloadFile(WorkbenchInterface $workbench, string $absolutePath, bool $relativeToSiteRoot = true)
    {
        // TODO route downloads over api/files and add an authorization point - see handle() method
        $installationPath = FilePathDataType::normalize($workbench->getInstallationPath());
        $absolutePath = FilePathDataType::normalize($absolutePath);
        if (StringDataType::startsWith($absolutePath, $installationPath) === false) {
            throw new FacadeRuntimeError('Cannot provide download link for file "' . $absolutePath . '"');
        }
        $relativePath = StringDataType::substringAfter($absolutePath, $installationPath);
        if ($relativeToSiteRoot) {
            return ltrim($relativePath, "/");
        } else {
            return $workbench->getUrl() . ltrim($relativePath, "/");
        }
    }    
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param bool $relativeToSiteRoot
     * @param string $properties
     * @return string
     */
    public static function buildUrlToDownloadData(MetaObjectInterface $object, string $uid, bool $relativeToSiteRoot = true, string $properties = null) : string
    {
        $facade = FacadeFactory::createFromString(__CLASS__, $object->getWorkbench());
        $url = $facade->getUrlRouteDefault() . '/' . $object->getAliasWithNamespace() . '/' . urlencode($uid);
        if ($properties) {
            $url .= '?'. $properties;
        }
        return $relativeToSiteRoot ? $url : $object->getWorkbench()->getUrl() . '/' . $url;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param bool $relativeToSiteRoot
     * @param string $properties
     * @return string
     */
    public static function buildUrlToOneTimeLink (MetaObjectInterface $object, string $uid, bool $relativeToSiteRoot = true, string $properties = null) : string
    {
        $facade = FacadeFactory::createFromString(__CLASS__, $object->getWorkbench());
        $cache = $facade->getOtlCachePool();        
        $rand = UUIDDataType::generateUuidV4('');        
        $data = [];
        $data['object_alias'] = $object->getAliasWithNamespace();
        $data['uid'] = $uid;
        $params = [];
        if ($properties) {
            parse_str($properties, $params);
        }
        $data['params'] = $params;        
        $cache->set($rand, $data);        
        $url = $facade->getUrlRouteDefault() . '/' . $facade->getOtlUrlPathPart() . '/' . urlencode($rand);
        return $relativeToSiteRoot ? $url : $object->getWorkbench()->getUrl() . '/' . $url;
    }
    
    /**
     * 
     * @param string $ident
     * @return ResponseInterface
     */
    public function createResponseFromOneTimeLinkIdent(string $ident) : ResponseInterface
    {        
        $exface = $this->getWorkbench();
        $cache = $this->getOtlCachePool();
        if ($cache->get($ident) === null) {
            $exface->getLogger()->logException(new FacadeRuntimeError("Cannot serve file for one time link ident '$ident'. No data found!"));
            return new Response(404, $this->buildHeadersCommon());
        }
        $data = $cache->get($ident, null);
        $objSel = $data['object_alias'];
        $uid = $data['uid'];
        $params = $data['params'];        
        $response = $this->createResponseFromObjectUid($objSel, $uid, $params);
        $cache->delete($ident);        
        return $response;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return MetaAttributeInterface|NULL
     */
    protected function findAttributeForContents(MetaObjectInterface $object) : ?MetaAttributeInterface
    {
        if ($fileBehavior = $object->getBehaviors()->getByPrototypeClass(FileBehavior::class)->getFirst()) {
            return $fileBehavior->getContentsAttribute();
        }
        
        $attrs = $object->getAttributes()->filter(function(MetaAttributeInterface $attr){
            return ($attr->getDataType() instanceof BinaryDataType);
        });
        
        return $attrs->count() === 1 ? $attrs->getFirst() : null;
    }
    
    /**
     *
     * @param MetaObjectInterface $object
     * @return MetaAttributeInterface|NULL
     */
    protected function findAttributeForFilename(MetaObjectInterface $object) : ?MetaAttributeInterface
    {
        if ($fileBehavior = $object->getBehaviors()->getByPrototypeClass(FileBehavior::class)->getFirst()) {
            return $fileBehavior->getFilenameAttribute();
        }
        
        $attrs = $object->getAttributes()->filter(function(MetaAttributeInterface $attr){
            return ($attr->getDataType() instanceof BinaryDataType);
        });
            
            return $attrs->count() === 1 ? $attrs->getFirst() : null;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return MetaAttributeInterface|NULL
     */
    protected function findAttributeForMimeType(MetaObjectInterface $object) : ?MetaAttributeInterface
    {
        if ($fileBehavior = $object->getBehaviors()->getByPrototypeClass(FileBehavior::class)->getFirst()) {
            return $fileBehavior->getMimeTypeAttribute();
        }
        
        $attrs = $object->getAttributes()->filter(function(MetaAttributeInterface $attr){
            return ($attr->getDataType() instanceof MimeTypeDataType);
        });
            
        return $attrs->count() === 1 ? $attrs->getFirst() : null;
    }
    
    protected function resizeImage(string $src, int $width, int $height)
    {
        $img = (new ImageManager())->make($src);
        $img->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        return $img->encode();
    }
    
    protected function createPlaceholderImage (string $text, int $width, int $height)
    {
        $img = (new ImageManager())->canvas($width, $height);
        $posY = $height/2;
        $posX = $width/2;
        $img->text($text, $posX, $posY, function($font) {
            //set style of text
            $font->file(5);
            $font->align('center');
        });
        return $img->encode();
    }
    
    /**
     * 
     * @return string
     */
    protected function getOtlCacheName() : string
    {
        return $this->otlCacheName;
    }
    
    /**
     * 
     * @return string
     */
    protected function getOtlUrlPathPart() : string
    {
        return $this->otlUrlPathPart;
    }
    
    protected function getOtlCachePool() : CacheInterface
    {
        return $this->getWorkbench()->getCache()->getPool($this->getOtlCacheName());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon() : array
    {
        return array_filter($this->getConfig()->getOption('FACADES.HTTPFILESERVERFACADE.HEADERS.COMMON')->toArray());
    }
}