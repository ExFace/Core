<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanUseProxyFacade;
use exface\Core\Widgets\Traits\iCanUseProxyFacadeTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\ImageUrlDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Uploader;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Traits\EditableTableTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\LogicException;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iCanEditData;
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;

/**
 * Shows a scrollable gallery of images as a horizontal or vertical strip.
 * 
 * This widget makes it easy to produce image galleries from data with image URLs: All 
 * you need to do, is define `image_url_attribute_alias` and optionally an 
 * `image_title_attribute_alias`. 
 * 
 * If the gallery shows an object with the `FileBehavior`, many properties related
 * to file attributes will be configured automatically.
 * 
 * The galleries can be oriented vertically or horizontally. Facades should produce
 * a scrollable column or row of similarly sized images, depending on the `orientation`
 * property. Horizontal galleries will have images of equal height, while vertical
 * galleries will equalize the width of all images.
 * 
 * Each image is a separate data item (comparable to a table row or a list item), so
 * it can be selected and passed to actions as input data. 
 * 
 * As any data widget, the image gallery can contain filters, columns, buttons, etc.
 * In particular, buttons can be used to navigate to business objects, represented
 * by the images (e.g. products) or to modify/delete image data. 
 * 
 * Additional columns may be added to the data widget manually: depending on the facade,
 * this additional information may be displayed in overlays or descriptions of some kind.
 * While this functionality is optional, the additional information however must be
 * passed to actions, performed on the meta object behind each image.
 * 
 * ## Upload/Download
 * 
 * Image galleries have `upload_enabled` and `download_enabled` properties to control
 * if the user should be able to upload or download images. The `uploader` settings
 * can be used to add file restrictions.
 * 
 * ## Non-image files
 * 
 * Image galleries also support file types other than images, but will not disply
 * thumbnails in this case. Non-images can be uploaded and downloaded however.
 * 
 * ## Examples
 * 
 * ### Simple gallery based on image files
 * 
 * It is most simple to create a gallery from an object, that has the `FileBehavior`. In this
 * case all attributes are determined automatically! Images will be served via the `HttpFileServerFacade`,
 * which will take care of security, resizing, etc.
 * 
 * ```
 * {
 *  "widget_type": "Imagegallery",
 *  "object_alias": "my.App.image_files",
 * }
 * 
 * ```
 * 
 * ### Gallery with custom image URLs
 * 
 * If your object already has image URLs as attributes (e.g. images hosted on a media server), you can 
 * use the explicitly. However, in this case, the image server will need to take care of authorization,
 * etc.!
 * 
 * ```
 * {
 *  "widget_type": "Imagegallery",
 *  "object_alias": "my.App.images",
 *  "image_url_attribute_alias": "uri",
 *  "thumbnail_url_attribute_alias": "thumb",
 *  "image_title_attribute_alias": "description"
 * }
 * 
 * ```
 * 
 * ### Interactive gallery with custom columns, filters and buttons
 * 
 * Here is an example with custom filters, columns and buttons. Note, that you need to
 * explicitly let the widget show a header, if you want your filters to be visible from
 * the start.Here we assume, that the object has `FileBehavior`.
 * 
 * ```
 * {
 *  "widget_type": "Imagegallery",
 *  "object_alias": "my.App.product_images",
 *  "hide_header": false,
 *  "filters": [
 *      {
 *          "attribute_alias": "product__category"
 *      }
 *  ],
 *  "columns": [
 *      {
 *          "attribute_alias": "product"
 *      }
 *  ],
 *  "buttons": [
 *      {
 *          "action_alias": "my.App.setPriceForProduct"
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * ### Gallery with up- and download
 * 
 * ```
 * {
 *  "widget_type": "Imagegallery",
 *  "object_alias": "my.App.product_images",
 *  "download_enabled": true,
 *  "upload_enabled": true,
 *  "uploader": {
 *      "instant_upload": false
 *  }
 * }
 * 
 * ```
 * 
 * ## Similar widgets and alternatives
 * 
 * There is another handy image widget called `ImageCarousel`: it adds a details-widget
 * (by default a large image) to the gallery, which is being displayed once a user
 * selects an image. Instead of a larger image, any other widget - even a `Form` can
 * be used to display details of a gallery image. Similarly to `Imagegallery` simplifying, 
 * image handling in `Data` widgets, the `ImageCarousel` makes it easy to use galleries within
 * `DataCarousel` widgets. 
 * 
 * If you prefer a waterfall-like (or pinterest-like) gallery, try using `DataCards` with the
 * image as your only visible widget within a card.
 * 
 * @author Andrej Kabachnik
 *
 */
class ImageGallery extends Data implements iCanEditData, iCanUseProxyFacade, iTakeInput
{
    use iCanUseProxyFacadeTrait;
    use EditableTableTrait;
    
    const ORIENTATION_HORIZONTAL = 'horizontal';
    const ORIENTATION_VERTICAL = 'vertical';

    private $imageUrlColumn = null;

    private $imageTitleColumn = null;
    
    private $imageUrlAttributeAlias = null;
    
    private $imageTitleAttributeAlias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $mimeTypeColumn = null;
    
    private $thumbnailUrlAttributeAlias = null;
    
    private $thumbnailUrlColumn = null;
    
    private $orientation = self::ORIENTATION_HORIZONTAL;
    
    private $uploader = null;
    
    private $uploaderUxon = null;
    
    private $uploadEnabled = false;
    
    private $downloadEnabled = true;
    
    private $filenameAttributeAlias = null;
    
    private $filenameColumn = null;
    
    private $zoom = true;
    
    private $zoomOnClick = false;
    
    private $filesFacade = null;
    
    private $checkedBehaviorForObject = null;
    
    protected function init()
    {
        parent::init();
        // Galleries have no headers or footer by default, but they can be
        // explicitly enabled by the user.
        $this->setHideHeader(true);
        $this->setHideFooter(true);
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getImageUrlColumn() : ?DataColumn
    {
        return $this->imageUrlColumn;
    }
    
    public function hasImageUrlColumn() : bool
    {
        return $this->imageTitleColumn !== null;
    }

    /**
     * 
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getImageTitleColumn() : DataColumn
    {
        if ($this->imageTitleAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->imageTitleColumn !== null) {
            return $this->imageTitleColumn;
        } 
        throw new WidgetConfigurationError($this, 'No data column to be used for image titles could be found!');
    }
    
    /**
     * 
     * @return bool
     */
    public function hasImageTitleColumn() : bool
    {
        return $this->imageTitleAttributeAlias !== null;
    }
    
    /**
     * The alias of the attribute with the image URLs
     * 
     * @uxon-property image_url_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageGallery
     */
    public function setImageUrlAttributeAlias(string $value) : Imagegallery
    {
        $this->imageUrlAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        // Make the column show images. This ensures backward compatibility to other data widget (e.g. DataTable),
        // so facades, that do not have a gallery implementation, can simply fall back to a table and it
        // would automatically show the images.
        $col->setCellWidget(new UxonObject([
            "widget_type" => "Image"
        ]));
        $this->addColumn($col);
        $this->imageUrlColumn = $col;       
        return $this;
    }
    
    /**
     * The alias of the attribute with the image titles.
     * 
     * If the gallery shows an object with `FileBehavior`, the filename attribute will be used for
     * `image_title_attribute_alias` by default.
     * 
     * @uxon-property image_title_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageGallery
     */
    public function setImageTitleAttributeAlias(string $value) : Imagegallery
    {
        $this->imageTitleAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->imageTitleColumn = $col;
        return $this;
    }
    
    /**
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getMimeTypeColumn() : DataColumn
    {
        if ($this->mimeTypeAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->mimeTypeColumn !== null) {
            return $this->mimeTypeColumn;
        }
        throw new WidgetConfigurationError($this, 'No data column with mime type found!');
    }
    
    public function hasMimeTypeColumn() : bool
    {
        if ($this->mimeTypeAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->mimeTypeAttributeAlias !== null;
    }
    
    /**
     * The attribute for the mime type - e.g. `application/pdf`, etc.
     *
     * If the gallery shows an object with `FileBehavior`, the `mime_type_attribute_alias`
     * will be determined automatically by default.
     * 
     * @uxon-property mime_type_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileList
     */
    public function setMimeTypeAttributeAlias(string $value) : ImageGallery
    {
        $this->mimeTypeAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->mimeTypeColumn = $col;
        return $this;
    }
    
    /**
     *
     * @return ?string
     */
    protected function getOrientation() : ?string
    {
        return $this->orientation;
    }
    
    /**
     * 
     * @return bool
     */
    public function isVertical() : bool
    {
        return $this->getOrientation() === self::ORIENTATION_VERTICAL;
    }
    
    /**
     * 
     * @return bool
     */
    public function isHorizontal() : bool
    {
        return $this->getOrientation() === self::ORIENTATION_HORIZONTAL;
    }
    
    /**
     * Makes the gallery vertically or horizontally oriented.
     * 
     * By default, the temaplate will set the orientation automatically. Use this property to override
     * the default orientation.
     * 
     * @uxon-property orientation
     * @uxon-type [vertical,horizontal]
     * 
     * @param ?string $value
     * @return ImageGallery
     */
    public function setOrientation(?string $value) : Imagegallery
    {
        $value = trim(strtolower($value));
        
        if ($value !== self::ORIENTATION_HORIZONTAL && $value !== self::ORIENTATION_VERTICAL) {
            throw new WidgetConfigurationError($this, 'Invalid Imagegallery orientation "' . $value . '": only "vertical" or "horizontal" are allowed!');
        }
        
        $this->orientation = $value;
        return $this;
    }
    
    public function isUploadEnabled() : bool
    {
        return $this->uploadEnabled;
    }
    
    /**
     * Enable or disable uploading
     * 
     * @uxon-property upload_enabled
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return ImageGallery
     */
    public function setUploadEnabled(bool $value) : ImageGallery
    {
        $this->uploadEnabled = $value;
        return $this;
    }
    
    public function getUploader() : Uploader
    {
        if ($this->uploader === null) {
            if ($this->isUploadEnabled() === false) {
                throw new LogicException('Cannot get the uploader for ' . $this->getWidgetType() . ': upload is generally disabled!');
            }
            if ($this->uploaderUxon === null) {
                throw new WidgetConfigurationError($this, 'Please configure the `uploader` option of widget "' . $this->getWidgetType() . '"!');
            }
            $this->uploader = new Uploader($this, $this->uploaderUxon);
        }
        return $this->uploader;
    }
    
    /**
     * Uploader configuration
     * 
     * @uxon-property uploader
     * @uxon-type \exface\Core\Widgets\Parts\Uploader
     * @uxon-template {"filename_attribute": "", "file_content_attribute": ""}
     * 
     * @param UxonObject $value
     * @return ImageGallery
     */
    public function setUploader(UxonObject $value) : ImageGallery
    {
        $this->uploaderUxon = $value;
        $this->uploadEnabled = true;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield from parent::getChildren();
        
        if ($this->isUploadEnabled()) {
            yield $this->getUploader()->getInstantUploadButton();
        }
        
        return;
    }
    
    /**
     * 
     * @return bool
     */
    public function isZoomable() : bool
    {
        return $this->zoom;
    }
    
    /**
     * Set to TRUE to enable a lightbox-style zoom effect
     * 
     * @uxon-property zoomable
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return ImageGallery
     */
    public function setZoomable(bool $value) : ImageGallery
    {
        $this->zoom = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isZoomOnClick() : bool
    {
        return $this->zoomOnClick;
    }
    
    /**
     * Set to TRUE to zoom when an image is clicked
     * 
     * @uxon-property zoom_on_click
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return ImageGallery
     */
    public function setZoomOnClick(bool $value) : ImageGallery
    {
        $this->zoomOnClick = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
    public function getWidth()
    {
        if ($this->isHorizontal() && parent::getWidth()->isUndefined()) {
            $this->setWidth(WidgetDimension::MAX);
        }
        return parent::getWidth();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHeight()
     */
    public function getHeight()
    {
        if (! $this->isHorizontal() && parent::getHeight()->isUndefined()) {
            $this->setHeight(WidgetDimension::MAX);
        }
        return parent::getHeight();
    }
    
    /**
     * In an ImageGallery readonly means it cannot upload, so there is no point in an
     * extra uxon-property here.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setReadonly()
     */
    public function setReadonly($true_or_false) : WidgetInterface
    {
        $this->setUploadEnabled(BooleanDataType::cast($true_or_false));
        return $this;
    }
    
    /**
     * An ImageGallery is readonly if it does not do upload as part of form data.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isReadonly()
     */
    public function isReadonly() : bool
    {
        return $this->isUploadEnabled() === false || $this->getUploader()->isInstantUpload();
    }
    
    /**
     * 
     * @return HttpFileServerFacade
     */
    protected function getFilesFacade() : HttpFileServerFacade
    {
        if ($this->filesFacade === null) {
            $this->filesFacade = FacadeFactory::createFromString(HttpFileServerFacade::class, $this->getWorkbench());
        }
        return $this->filesFacade;
    }
    
    /**
     * 
     * @param string $uid
     * @param string $width
     * @param string $height
     * @param bool $relativeToSiteRoot
     * @return string
     */
    public function buildUrlForImage(string $uid = null, string $width = null, string $height = null, bool $relativeToSiteRoot = true) : string
    {
        if ($uid === null) {
            $uid = '[#' . $this->getUidColumn()->getDataColumnName() . '#]';
        }
        $url = HttpFileServerFacade::buildUrlToDownloadData($this->getMetaObject(), $uid, null, false, $relativeToSiteRoot);
        if ($width !== null && $height !== null) {
            $url .= "?&resize={$width}x{$height}";
        }
        return $url;
    }
    
    /**
     * 
     * @return string
     */
    public function getThumbnailUrlColumn() : ?DataColumn
    {
        return $this->thumbnailUrlColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasThumbnailUrlColumn() : bool
    {
        return $this->thumbnailUrlAttributeAlias !== null;
    }

    /**
     * Alias of the attribute, that contains a custom thumbnail URL
     * 
     * If not set, a thumbnail will be generated automatically via `HttpFileServerFacade`.
     * 
     * @uxon-property thumbnail_url_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageGallery
     */
    public function setThumbnailUrlAttributeAlias(string $value) : ImageGallery
    {
        $this->thumbnailUrlAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->thumbnailUrlColumn = $col;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isDownloadEnabled() : bool
    {
        return $this->downloadEnabled;
    }
    
    /**
     * Set to FALSE to remove the download button
     * 
     * @uxon-property download_enabled
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return ImageGallery
     */
    public function setDownloadEnabled(bool $value) : ImageGallery
    {
        $this->downloadEnabled = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getFilenameColumn() : ?DataColumn
    {
        if ($this->filenameAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->filenameColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasFilenameColumn() : bool
    {
        if ($this->filenameAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->filenameAttributeAlias !== null;
    }
    
    /**
     * Alias of the attribute containing the file name (e.g. for downloads).
     * 
     * If the gallery shows an object with `FileBehavior`, the `filename_attribute_alias`
     * will be determined automatically by default.
     * 
     * @uxon-property filename_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageGallery
     */
    public function setFilenameAttributeAlias(string $value) : ImageGallery
    {
        $this->filenameAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->filenameColumn = $col;
        return $this;
    }
    
    protected function guessColumns()
    {
        /* @var $behavior \exface\Core\Behaviors\FileBehavior */
        if ($this->checkedBehaviorForObject !== $this->getMetaObject() && null !== $behavior = $this->getMetaObject()->getBehaviors()->getByPrototypeClass(FileBehaviorInterface::class)->getFirst()) {
            if ($this->filenameColumn === null && $attr = $behavior->getFilenameAttribute()) {
                $this->setFilenameAttributeAlias($attr->getAlias());
            }
            
            if ($this->imageTitleColumn === null && $attr = $behavior->getFilenameAttribute()) {
                $this->setImageTitleAttributeAlias($attr->getAlias());
            }
            
            if ($this->mimeTypeColumn === null && $attr = $behavior->getMimeTypeAttribute()) {
                $this->setMimeTypeAttributeAlias($attr->getAlias());
            }
            
            if ($this->imageUrlColumn === null) {
                foreach ($this->getColumns() as $col) {
                    if ($col->getDataType() instanceof ImageUrlDataType) {
                        $this->imageUrlColumn = $col;
                        break;
                    }
                }
            }
        }
        $this->checkedBehaviorForObject = $this->getMetaObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $dataSheet = null) : DataSheetInterface
    {
        $this->guessColumns();
        return parent::prepareDataSheetToPrefill($dataSheet);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $dataSheet = null) : DataSheetInterface
    {
        $this->guessColumns();
        return parent::prepareDataSheetToRead($dataSheet);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getActionDataColumnNames()
     */
    public function getActionDataColumnNames() : array
    {
        $this->guessColumns();
        $cols = parent::getActionDataColumnNames();
        if ($this->isUploadEnabled()) {
            $cols = array_merge($cols, $this->getUploader()->getActionDataColumnNames());
        }
        return array_unique($cols);
    }
}