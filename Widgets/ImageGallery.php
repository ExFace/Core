<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanUseProxyFacade;
use exface\Core\Widgets\Traits\iCanUseProxyFacadeTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\ImageUrlDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Uploader;
use exface\Core\CommonLogic\WidgetDimension;

/**
 * Shows a scrollable gallery of images as a horizontal or vertical strip.
 * 
 * This widget makes it easy to produce image galleries from data with image URLs: All 
 * you need to do, is define `image_url_attribute_alias` and optionally an 
 * `image_title_attribute_alias`.
 * 
 * The galleries can be oriented vertically or horizontally. Facades should produce
 * a scrollable column or row of similarly sized images, depending on the `orientation`
 * property. Horizontal galleries will have images of equal height, while vertical
 * galleries will equalize the width of all images.
 * 
 * Each image is a separate data item (comparable to a table row or a list item), so
 * it can be selected and passed to actions as input data. 
 * 
 * Facades may also provide additional image-specific functionality like uploading.
 * 
 * The following simple exmple will produce a default gallery (the facade will choose
 * it's orientation):
 * 
 * ```
 * {
 *  "widget_type": "Imagegallery",
 *  "object_alias": "my.App.images",
 *  "image_url_attribute_alias": "uri",
 *  "image_title_attribute_alias": "description"
 * }
 * 
 * ```
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
 * Here is an example with custom filters, columns and buttons. Note, that you need to
 * explicitly let the widget show a header, if you want your filters to be visible from
 * the start.
 * 
 * ```
 * {
 *  "widget_type": "Imagegallery",
 *  "object_alias": "my.App.product_images",
 *  "image_url_attribute_alias": "uri",
 *  "image_title_attribute_alias": "description",
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
class ImageGallery extends Data implements iCanUseProxyFacade
{
    use iCanUseProxyFacadeTrait;
    
    const ORIENTATION_HORIZONTAL = 'horizontal';
    const ORIENTATION_VERTICAL = 'vertical';

    private $image_url_column = null;

    private $image_title_column = null;
    
    private $image_url_attribute_alias = null;
    
    private $image_title_attribute_alias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $mimeTypeColumn = null;
    
    private $orientation = self::ORIENTATION_HORIZONTAL;
    
    private $uploader = null;
    
    private $uploaderUxon = null;
    
    private $uploadEnabled = false;
    
    private $zoom = false;
    
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
     * @return DataColumn
     */
    public function getImageUrlColumn() : DataColumn
    {
        if ($this->image_url_column !== null) {
            return $this->image_url_column;
        } else {
            foreach ($this->getColumns() as $col) {
                if ($col->getDataType() instanceof ImageUrlDataType) {
                    $this->image_url_column = $col;
                    return $col;
                }
            }
        }
        throw new WidgetConfigurationError($this, 'No data column to be used for image URLs could be found!');
    }

    /**
     * 
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getImageTitleColumn() : DataColumn
    {
        if ($this->image_title_column !== null) {
            return $this->image_title_column;
        } 
        throw new WidgetConfigurationError($this, 'No data column to be used for image titles could be found!');
    }
    
    /**
     * 
     * @return bool
     */
    public function hasImageTitleColumn() : bool
    {
        return $this->image_title_attribute_alias !== null;
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
        $this->image_url_attribute_alias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        // Make the column show images. This ensures backward compatibility to other data widget (e.g. DataTable),
        // so facades, that do not have a gallery implementation, can simply fall back to a table and it
        // would automatically show the images.
        $col->setCellWidget(new UxonObject([
            "widget_type" => "Image"
        ]));
        $this->addColumn($col);
        $this->image_url_column = $col;       
        return $this;
    }
    
    /**
     * The alias of the attribute with the image titles
     * 
     * @uxon-property image_title_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageGallery
     */
    public function setImageTitleAttributeAlias(string $value) : Imagegallery
    {
        $this->image_title_attribute_alias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->image_title_column = $col;
        return $this;
    }
    
    /**
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getMimeTypeColumn() : DataColumn
    {
        if ($this->mimeTypeColumn !== null) {
            return $this->mimeTypeColumn;
        }
        throw new WidgetConfigurationError($this, 'No data column with mime type found!');
    }
    
    public function hasMimeTypeColumn() : bool
    {
        return $this->mimeTypeAttributeAlias !== null;
    }
    
    /**
     * The attribute for the mime type - e.g. `application/pdf`, etc.
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
            if ($this->uploaderUxon === null) {
                throw new WidgetConfigurationError('Please configure the `uploader` option of widget "' . $this->getWidgetType() . '"!');
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
     * Set to TRUE to enable a lightbox-style zoom effect on click
     * 
     * @uxon-property zoomable
     * @uxon-type boolean
     * @uxon-default false
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
}