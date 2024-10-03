<?php
namespace exface\Core\Facades;

use exface\Core\Events\Workbench\OnBeforeStopEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\DataTypes\FilePathDataType;
use Intervention\Image\ImageManager;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\MimeTypeDataType;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\AbstractHttpFacade\Middleware\OneTimeLinkMiddleware;
use Psr\SimpleCache\CacheInterface;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Filesystem\DataSourceFileInfo;
use exface\Core\CommonLogic\Filesystem\LocalFileInfo;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\CommonLogic\Filesystem\InMemoryFile;
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\Exceptions\Filesystem\FileCorruptedError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Facade to upload and download files using virtual pathes.
 * 
 * ## Examples
 * 
 * - Download a file via object alias and UID
 *      - `api/files/download/my.App.OBJECT_ALIAS/0x5468789`
 *      - `api/files/download/my.App.OBJECT_ALIAS/0x5468789`
 * - Stream/embed a file via object alias and UID
 *      - `api/files/view/my.App.OBJECT_ALIAS/0x5468789`
 * - Thumbnail with a ceratin size via object alias and UID
 *      - `api/files/thumb/160x100/my.App.OBJECT_ALIAS/0x5468789`
 *      - `api/files/thumb/x100/my.App.OBJECT_ALIAS/0x5468789`
 *      - `api/files/thumb/160x/my.App.OBJECT_ALIAS/0x5468789`
 * - Download a temp file
 *      - `api/files/pickup/<path_relative_to_temp_folder>`
 * - One-time links to get access without explicit authorization (e.g. for a PDF generators)
 *      - `api/files/otl/my.App.OBJECT_ALIAS/0x5468789>`
 * - Legacy URLs
 *      - `api/files/my.App.OBJECT/0x687654698`
 *      - `api/files/axenox.DevMan.ticket_file/base64%2CZGF0YS9...%3D%3D?&resize=260x190``
 * 
 * ## File location and references
 * 
 * Use the follosing url `api/files/my.App.OBJECT_ALIAS/uid` to download a file with the given `uid` value.
 * 
 * ### Encoding of UIDs
 * 
 * UID values MUST be properly encoded:
 * 
 * - URL encoded - unless they contain slashes (as many servers incl. Apache do not allow URL encoded slashes for security reasons)
 * - Base64URL encoded with prefix `base64URL,` AND URL encoded on top: i.e. `url_encode(Base64URL(url_encode(value)))`. This is the 
 * most reliable way to pass the UID value, but is not readable at all. For backwards compatibility also Base64 encoded values are 
 * supported with prefix `Base64,`.
 * 
 * ## Request types
 * 
 * You can resize images by adding the URL parameter `&resize=WIDTHxHEIGHT`.
 * 
 * ## Upload
 * 
 * Not available yet
 * 
 * ## Access restrictions
 * 
 * This facade can be accessed by any authenticated (logged in) user by default. Please modify authorization policies if required!
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpFileServerFacade extends AbstractHttpFacade
{   
    const URL_PATH_DOWNLOAD = 'download';
    const URL_PATH_VIEW = 'view';
    const URL_PATH_OTL = 'otl';
    const URL_PATH_THUMB = 'thumb';
    const URL_PATH_PICKUP = 'pickup';
    const URL_PATH_TEMP = 'temp';
    
    const CACHE_POOL_OTL = '_onetimelink';
    
    const DEFAULT_THUMBNAIL_WIDTH = 120;

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
        // See if there are additional parameters
        $params = [];
        parse_str($uri->getQuery() ?? '', $params);
        
        $pathStart = mb_strtolower($pathParts[0]);
        $options = null;
        $noCache = false;
        switch ($pathStart) {
            case self::URL_PATH_TEMP:
            case self::URL_PATH_DOWNLOAD:
            case self::URL_PATH_VIEW:
                $mode = array_shift($pathParts);
                break;
            case self::URL_PATH_THUMB:
                $mode = array_shift($pathParts);
                $options = array_shift($pathParts);
                break;
            case self::URL_PATH_OTL:
                // Should this ever happen? One-time-links are handled by the middleware, aren't they?
            default:
                $mode = self::URL_PATH_DOWNLOAD;
                // $pathParts remain untouched!
        }
        
        // Support for legacy resizing options, that still may be used somewhere via `=FileLink()` formula
        if ($params['resize'] !== null) {
            $mode = self::URL_PATH_THUMB;
            $options = $params['resize'];
        }
        
        switch (true) {
            case $mode === self::URL_PATH_TEMP:
                $filename = urldecode(implode('/', $pathParts));
                $fileInfo = new LocalFileInfo($this->getWorkbench()->filemanager()->getPathToCacheFolder() . '/' . $filename);
                $noCache = true;
                // Delete the temp file once it was downloaded
                $this->getWorkbench()->eventManager()->addListener(OnBeforeStopEvent::getEventName(), function(OnBeforeStopEvent $event) use ($fileInfo) {
                    @unlink($fileInfo->getPathAbsolute());
                });
                break;
            case count($pathParts) === 2:
                $objSel = urldecode($pathParts[0]);
                $uid = urldecode($pathParts[1]);
                // Decode UID if it is Base64 - this will be the case if the UID has special characters
                // like slashes - they might be considered insecure by some servers, so the request
                // will not be processed if they are not encoded
                switch (true) {
                    case StringDataType::startsWith($uid, 'base64URL,'):
                        $uid = BinaryDataType::convertBase64URLToText(substr($uid, 10), true);
                        break;
                    case StringDataType::startsWith($uid, 'base64,'):
                        $uid = BinaryDataType::convertBase64ToText(substr($uid, 7));
                        break;
                }
                $fileInfo = DataSourceFileInfo::fromObjectSelectorAndUID($this->getWorkbench(), $objSel, $uid);
                break;
            default:
                $fileInfo = new LocalFileInfo($pathParts[0]);
                break;
        }
        
        if ($noCache === false && null !== $cacheInfo = $this->getCache($fileInfo, $options)) {
            $fileInfo = $cacheInfo;
        }
        
        switch (true) {
            case $mode === self::URL_PATH_TEMP:
            case $mode === self::URL_PATH_PICKUP:
            case $mode === self::URL_PATH_DOWNLOAD:
                $response = $this->createResponseForDonwload($fileInfo);
                break;
            case $mode === self::URL_PATH_THUMB && ($cacheInfo === null || $noCache === true):
                list($width, $height) = explode('x', $options);
                $width = $width === '' ? null : intval($width);
                $height = $height === '' ? null : intval($height);
                $fileInfo = $this->createThumbnail($fileInfo, $width, $height);
                $response = $this->createResponseForEmbedding($fileInfo);
                break;
            case $mode === self::URL_PATH_THUMB:
            case $mode === self::URL_PATH_VIEW:
                $response = $this->createResponseForEmbedding($fileInfo);
                break;
        }
        
        // IDEA Only use cache for non-local files? What is the point of caching local files?
        if ($noCache === false && $cacheInfo === null) {
            $this->setCache($fileInfo, $options);
        }
        
        return $response;        
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @param string $options
     * @return FileInfoInterface|NULL
     */
    protected function getCache(FileInfoInterface $fileInfo, string $options = null) : ?FileInfoInterface
    {
        $cachePath = $this->getCachePath($fileInfo, $options);
        $cacheInfo = null;
        if ($cachePath !== null && file_exists($cachePath)) {
            $cacheInfo = new LocalFileInfo($cachePath);
            // Check if the cache not older than the original
            if ($cacheInfo->getMTime() <= $fileInfo->getMTime()) {
                return null;
            }
            // Check if the cache actually contains data
            if (empty($cacheInfo->openFile()->read())) {
                return null;
            }
        }
        return $cacheInfo;
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @return HttpFileServerFacade
     */
    protected function setCache(FileInfoInterface $fileInfo, string $options = null) : HttpFileServerFacade
    {
        $cachePath = $this->getCachePath($fileInfo, $options);
        $folder = FilePathDataType::findFolderPath($cachePath);
        if (! is_dir($folder)) {
            Filemanager::pathConstruct($folder);
        }
        file_put_contents($cachePath, $fileInfo->openFile()->read());
        return $this;
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @return ResponseInterface
     */
    protected function createResponseFromFile(FileInfoInterface $fileInfo) : ResponseInterface
    {
        $type = $fileInfo->getMimetype();
        if (null !== $type) {
            $headers = $this->buildHeadersCommon(MimeTypeDataType::isBinary($type));
            $headers['Content-Type'] = $type;
        } else {
            $headers = $this->buildHeadersCommon(false);            
        }
        return new Response(200, $headers, $fileInfo->openFile()->readStream());
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @return ResponseInterface
     */
    public function createResponseForDonwload(FileInfoInterface $fileInfo) : ResponseInterface
    {
        $response = $this->createResponseFromFile($fileInfo);
        $response = $response->withHeader('Content-Disposition', "attachment; filename=" . $fileInfo->getFilename());
        return $response;
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @return ResponseInterface
     */
    protected function createResponseForEmbedding(FileInfoInterface $fileInfo) : ResponseInterface
    {
        $response = $this->createResponseFromFile($fileInfo);
        $response = $response->withHeader('Content-Disposition', 'inline; filename=' . $fileInfo->getFilename());
        return $response;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon(bool $asBinary = false) : array
    {
        $facadeHeaders = array_filter($this->getConfig()->getOption('FACADES.HTTPFILESERVERFACADE.HEADERS.COMMON')->toArray());
        $commonHeaders = parent::buildHeadersCommon();
        $headers = array_merge(
            $commonHeaders, 
            $facadeHeaders,      
            [
                'Expires' => 0,
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Pragma' => 'public'
            ]
        );
        
        if ($asBinary === true) {
            $headers['Content-Transfer-Encoding'] = 'binary';
        }
        
        return $headers;
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @param string $options
     * @return string
     */
    protected function getCachePath(FileInfoInterface $fileInfo, string $options = null) : string
    {
        if ($fileInfo instanceof LocalFileInfo) {
            $path = $fileInfo->getFolderInfo()->getPathRelative();
        } else {
            $path = $fileInfo->getFolderPath();
        }
        
        $ext = $fileInfo->getExtension();
        $ext = (! empty($ext) ? '.' : '') . $ext;
        $filename = $fileInfo->getFilename(false);
        $filename = $filename . (! empty($options) ? '_' . $options : '');
        
        $path = str_replace('://', DIRECTORY_SEPARATOR, $path);
        $path = str_replace(':', '_', $path);
        $path = $this->getWorkbench()->filemanager()->getPathToCacheFolder()
            . DIRECTORY_SEPARATOR . 'HttpFileServerFacade'
            . DIRECTORY_SEPARATOR . FilePathDataType::normalize($path, DIRECTORY_SEPARATOR) 
            . (empty($filename) ? '' : DIRECTORY_SEPARATOR . $filename) 
            . $ext;
        
        return $path;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getMiddleware()
     */
    protected function getMiddleware() : array
    {
        $middleware = parent::getMiddleware();
        $middleware[] = new OneTimeLinkMiddleware($this, self::URL_PATH_OTL);
        
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
        $relativePath = ltrim($relativePath, "/");
        $cachePath = FilePathDataType::normalize($workbench->filemanager()->getPathToCacheFolder()) . '/';

        if (StringDataType::startsWith($absolutePath, $cachePath)) {
            $facade = FacadeFactory::createFromString(__CLASS__, $workbench);
            $urlEnd = urlencode(StringDataType::substringAfter($absolutePath, $cachePath));
            // IMPORTANT: Decode `/` characters back because Apache and nginx will treat urlencoded 
            // slashes in the path differently and will issues 404 errors themselves.
            $urlEnd = str_replace('%2F', '/', $urlEnd);
            $urlPath = $facade->getUrlRouteDefault() . '/' . self::URL_PATH_TEMP . '/' . $urlEnd;
        } else {
            $urlPath = $relativePath;
        }

        if ($relativeToSiteRoot) {
            return $urlPath;
        } else {
            return $workbench->getUrl() . $urlPath;
        }
    }    
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param string $urlParams
     * @param bool $urlEncodeUid
     * @param bool $relativeToSiteRoot
     * @return string
     */
    public static function buildUrlToDownloadData(MetaObjectInterface $object, string $uid, string $urlParams = null, bool $urlEncodeUid = true, bool $relativeToSiteRoot = true) : string
    {
        $url = static::buildUrlForObjectUid($object, $uid, self::URL_PATH_DOWNLOAD, $urlEncodeUid);
        if ($urlParams) {
            $url .= '?'. $urlParams;
        }
        return $relativeToSiteRoot ? $url : $object->getWorkbench()->getUrl() . '/' . $url;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param int $width
     * @param int $height
     * @param bool $urlEncodeUid
     * @param bool $relativeToSiteRoot
     * @return string
     */
    public static function buildUrlToThumbnail(MetaObjectInterface $object, string $uid, int $width = null, int $height = null, bool $urlEncodeUid = true, bool $relativeToSiteRoot = true) : string
    {
        $resize = ($width ?? '') . 'x' . ($height ?? '');
        $url = static::buildUrlForObjectUid($object, $uid, (self::URL_PATH_THUMB . '/' . $resize), $urlEncodeUid);
        return $relativeToSiteRoot ? $url : $object->getWorkbench()->getUrl() . '/' . $url;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param string $path
     * @param bool $urlEncodeUid
     * @return string
     */
    protected static function buildUrlForObjectUid(MetaObjectInterface $object, string $uid, string $path = '', bool $urlEncodeUid = true) : string
    {
        $facade = FacadeFactory::createFromString(__CLASS__, $object->getWorkbench());
        $url = $facade->getUrlRouteDefault() . ($path === null ? '' : '/' . $path);
        $url .= '/' . $object->getAliasWithNamespace() . '/' . ($urlEncodeUid ? urlencode($uid) : $uid);
        return $url;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param bool $relativeToSiteRoot
     * @param string $urlParams
     * @return string
     */
    public static function buildUrlToOneTimeLink(WorkbenchInterface $workbench, string $url, bool $relativeToSiteRoot = true) : string
    {
        $facade = FacadeFactory::createFromString(__CLASS__, $workbench);
        $cache = $facade->getOtlCachePool();        
        $rand = UUIDDataType::generateUuidV4('');      
        $cache->set($rand, $url);        
        $otl = $facade->getUrlRouteDefault() . '/' . self::URL_PATH_OTL . '/' . urlencode($rand);
        return $relativeToSiteRoot ? $otl : $workbench->getUrl() . '/' . $otl;
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
        $url = $cache->get($ident, null);
        if (null === $url) {
            $exface->getLogger()->logException(new FacadeRuntimeError("Cannot serve file for one time link ident '$ident'. No data found!"));
            return new Response(404, $this->buildHeadersCommon());
        }
        $request = new ServerRequest('GET', $url);
        $response = $this->createResponse($request);
        $cache->delete($ident);        
        return $response;
    }
    
    /**
     * 
     * @param FileInfoInterface $fileInfo
     * @param int $width
     * @param int $height
     * @return FileInfoInterface
     */
    protected function createThumbnail(FileInfoInterface $fileInfo, int $width = null, int $height = null) : FileInfoInterface
    {
        switch (true) {
            case stripos($fileInfo->getMimetype(), 'image') !== false:
                try {
                    $img = (new ImageManager())->make($fileInfo->openFile()->read());
                    
                    if ($width === null && $height === null) {
                        $width = static::DEFAULT_THUMBNAIL_WIDTH;                       
                    }
                    $img->resize($width, $height, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    $binary = $img->encode();
                    $fileInfo = new InMemoryFile($binary, $fileInfo->getPathAbsolute(), $fileInfo->getMimetype());
                    break;
                } catch (\Throwable $e) {
                    if ($fileInfo->getSize() === NULL || $fileInfo->getSize() == 0) {
                        $this->getWorkbench()->getLogger()->logException(new FileCorruptedError(
                            'Can not create thumbnail, size of the file is 0 bytes!',
                            null,
                            $e,
                            $fileInfo));
                    } else {
                        $this->getWorkbench()->getLogger()->logException(new FileCorruptedError(
                            'Can not create thumbnail, file is probably corrupted!',
                            null,
                            $e,
                            $fileInfo), LoggerInterface::ERROR);
                    }
                }
            // IDEA add other thumbnails here - for office documents, pdfs, etc.?
            default:
                $extension = $fileInfo->getExtension();
                $text = mb_strtoupper($extension) ?? 'FILE';
                
                if ($width === null) {
                    $width = $height ?? static::DEFAULT_THUMBNAIL_WIDTH;
                }
                if ($height === null) {
                    $height = $width;
                }
                $binary = $this->createThumbnailAsPlaceholder($text, $width, $height);
                $thumbPath = $fileInfo->getPathAbsolute();
                if ($extension) {
                    $thumbPath = substr($thumbPath, 0, ((-1) * strlen($extension)));
                }
                $thumbPath .= 'jpg';
                $fileInfo = new InMemoryFile($binary, $thumbPath, 'image/jpeg');
                break;
        }
        return $fileInfo;
    }
    
    /**
     * 
     * @param string $text
     * @param int $width
     * @param int $height
     * @return string
     */
    protected function createThumbnailAsPlaceholder (string $text, int $width, int $height) : string
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
     * @return CacheInterface
     */
    protected function getOtlCachePool() : CacheInterface
    {
        return $this->getWorkbench()->getCache()->getPool(self::CACHE_POOL_OTL);
    }
}