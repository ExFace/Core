<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
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
 * ## Upload
 * 
 * Not available yet
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpFileServerFacade extends AbstractHttpFacade
{    
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
     * @return string
     */
    public static function buildUrlToDownloadData(MetaObjectInterface $object, string $uid, bool $relativeToSiteRoot = true) : string
    {
        $facade = FacadeFactory::createFromString(__CLASS__, $object->getWorkbench());
        $url = $facade->getUrlRouteDefault() . '/' . $object->getAliasWithNamespace() . '/' . urlencode($uid);
        return $relativeToSiteRoot ? $url : $object->getWorkbench()->getUrl() . '/' . $url;
    }

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
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = new HttpRequestHandler(new NotFoundHandler());
        
        // Authenticate users
        $handler->add(new AuthenticationMiddleware($this));
        
        $uri = $request->getUri();
        $path = ltrim(StringDataType::substringAfter($uri->getPath(), $this->getUrlRouteDefault()), "/");
        
        $pathParts = explode('/', $path);
        $objSel = urldecode($pathParts[0]);
        $uid = urldecode($pathParts[1]);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objSel);
        if (! $ds->getMetaObject()->hasUidAttribute()) {
            $this->getWorkbench()->getLogger()->logException(new FacadeRuntimeError('Cannot serve file from object ' . $ds->getMetaObject()->__toString() . ': object has no UID attribute!'));
            return new Response(404);
        }
        
        $colFilename = null;
        $colMime = null;
        $colContents = null;
        $attr = $this->findAttributeForContents($ds->getMetaObject());
        if ($attr) {
            $colContents = $ds->getColumns()->addFromAttribute($attr);
        } else {
            $this->getWorkbench()->getLogger()->logException(new FacadeRuntimeError());
            return new Response(404);
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
            return new Response(404);
        }
        
        $contentType = $colContents->getDataType();
        $binary = null;
        $plain = null;
        $headers = [
            'Expires' => 0,
            'Cache-Control', 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public'
        ];
        switch (true) {
            case $contentType instanceof BinaryDataType:
                $binary = $colContents->getDataType()->convertToBinary($colContents->getValue(0));
                $headers['Content-Transfer-Encoding'] = 'binary';
                break;
            default:
                $plain = $colContents->getValue(0);
                break;
        }
        
        
        // See if there are additional parameters 
        $params = [];
        parse_str($uri->getQuery() ?? '', $params);
        
        // Resize images
        if ($binary !== null && null !== $resize = $params['resize'] ?? null) {
            list($width, $height) = explode('x', $resize);
            $binary = $this->resizeImage($binary, $width, $height);
        }
        
        // Create a response
        if ($colMime !== null) {
            $headers['Content-Type'] = $colMime->getValue(0);
        }
        if ($colFilename !== null) {
            $headers['Content-Disposition'] = 'attachment; filename=' . $colFilename->getValue(0);
        }
        
        $response = new Response(200, $headers, stream_for($binary ?? $plain));
        return $response;
        
        return $handler->handle($request);
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
}