<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanUseProxyTemplate;
use exface\Core\Widgets\Traits\iCanUseProxyTemplateTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\ImageUrlDataType;
use exface\Core\CommonLogic\UxonObject;

/**
 * Shows a scrollable gallery of images as a horizontal or vertical strip.
 * 
 * This widget makes it easy to produce image galleries from data with image URLs: All 
 * you need to do, is define `image_url_attribute_alias` and optionally an 
 * `image_title_attribute_alias`.
 * 
 * The galleries can be oriented vertically or horizontally. Templates should produce
 * a scrollable column or row of similarly sized images, depending on the `orientation`
 * property. Horizontal galleries will have images of equal height, while vertical
 * galleries will equalize the width of all images.
 * 
 * Each image is a separate data item (comparable to a table row or a list item), so
 * it can be selected and passed to actions as input data. 
 * 
 * Templates may also provide additional image-specific functionality like uploading.
 * 
 * The following simple exmple will produce a default gallery (the template will choose
 * it's orientation):
 * 
 * ```
 * {
 *  "widget_type": "DataImageGallery",
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
 * Additional columns may be added to the data widget manually: depending on the template,
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
 *  "widget_type": "DataImageGallery",
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
 * be used to display details of a gallery image. Similarly to `DataImageGallery` simplifying, 
 * image handling in `Data` widgets, the `ImageCarousel` makes it easy to use galleries within
 * `DataCarousel` widgets. 
 * 
 * If you prefer a waterfall-like (or pinterest-like) gallery, try using `DataCards` with the
 * image as your only visible widget within a card.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataImageGallery extends Data implements iCanUseProxyTemplate
{
    use iCanUseProxyTemplateTrait;
    
    const ORIENTATION_HORIZONTAL = 'horizontal';
    const ORIENTATION_VERTICAL = 'vertical';

    private $image_url_column = null;

    private $image_title_column = null;
    
    private $image_url_attribute_alias = null;
    
    private $image_title_attribute_alias = null;
    
    private $orientation = null;
    
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
     * @return DataImageGallery
     */
    public function setImageUrlAttributeAlias(string $value) : DataImageGallery
    {
        $this->image_url_attribute_alias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
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
     * @return DataImageGallery
     */
    public function setImageTitleAttributeAlias(string $value) : DataImageGallery
    {
        $this->image_title_attribute_alias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        // Make the column show images. This ensures backward compatibility to other data widget (e.g. DataTable),
        // so templates, that do not have a gallery implementation, can simply fall back to a table and it
        // would automatically show the images.
        $col->setCellWidget(new UxonObject([
            "widget_type" => "Image"
        ]));
        $this->addColumn($col);
        $this->image_title_column = $col;
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
     * @return DataImageGallery
     */
    public function setOrientation(?string $value) : DataImageGallery
    {
        $value = trim(strtolower($value));
        
        if ($value !== self::ORIENTATION_HORIZONTAL && $value !== self::ORIENTATION_VERTICAL) {
            throw new WidgetConfigurationError($this, 'Invalid DataImageGallery orientation "' . $value . '": only "vertical" or "horizontal" are allowed!');
        }
        
        $this->orientation = $value;
        return $this;
    }
}