<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\ImageUrlDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
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
use exface\Core\Interfaces\Widgets\iFillEntireContainer;

/**
 * BETA! Shows a feed of comments (as seen in blogs or news pages) with a on option of quickly adding a comment
 * 
 * ## Examples
 * 
 * ### Simple comments
 * 
 * ```
 * {
 *    "widget_type": "CommentsFeed",
 *    "object_alias": "my.App.COMMENTS",
 *    "comment_id_attribute_alias": "UID",
 *    "comment_created_date_attribute_alias": "CREATED_ON",
 *    "comment_edited_date_attribute_alias": "MODIFY_ON",
 *    "comment_author_id_attribute_alias": "CREATED_BY",
 *    "comment_author_attribute_alias": "CREATED_BY__FULL_NAME",
 *    "comment_title_attribute_alias": "TITLE",
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class CommentsFeed extends Data implements iCanEditData, iTakeInput, iFillEntireContainer
{
    use EditableTableTrait;
    const ORIENTATION_HORIZONTAL = 'horizontal';
    const ORIENTATION_VERTICAL = 'vertical';

    private $imageUrlColumn = null;

    private $imageUrlAttributeAlias = null;
    
    private $commentTitleColumn = null;
    
    private $commentTitleAttributeAlias = null;
    
    private $commentCreatedDateColumn = null;
    
    private $commentCreatedDateAttributeAlias = null;
    
    private $commentEditedDateColumn = null;
    
    private $commentEditedDateAttributeAlias = null;
    
    private $commentAuthorColumn = null;
    
    private $commentAuthorAttributeAlias = null;
    
    private $commentIdColumn = null;
    
    private $commentIdAttributeAlias = null;

    private $commentAuthorIdColumn = null;
    
    private $commentAuthorIdAttributeAlias = null;
    
    private $isCurrentUserAuthor = false;
    
    private $orientation = self::ORIENTATION_HORIZONTAL;
    
    private $uploader = null;
    
    private $uploaderUxon = null;
    
    private $uploadEnabled = false;
    
    private $downloadEnabled = true;
    
    private $filenameAttributeAlias = null;
    
    private $filenameColumn = null;
     
    private $filesFacade = null;
    
    private $checkedBehaviorForObject = null;
    
    protected function init()
    {
        parent::init();
        // Comments have no headers or footer by default, but they can be
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
        return $this->commentTitleColumn !== null;
    }

    /**
     * 
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentTitleColumn() : DataColumn
    {
        if ($this->commentTitleAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentTitleColumn !== null) {
            return $this->commentTitleColumn;
        } 
        throw new WidgetConfigurationError($this, 'No data column to be used for comment titles could be found!');
    }
    
    /**
     * 
     * @return bool
     */
    public function hasCommentTitleColumn() : bool
    {
        if ($this->commentTitleAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentTitleAttributeAlias !== null;
    }
    
    /**
     * The alias of the attribute with the image titles.
     * 
     * If the gallery shows an object with `FileBehavior`, the filename attribute will be used for
     * `image_title_attribute_alias` by default.
     * 
     * @uxon-property comment_title_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentTitleAttributeAlias(string $value) : CommentsFeed
    {
        $this->commentTitleAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentTitleColumn = $col;
        return $this;
    }
    
    /**
     * The alias of the attribute with the image URLs
     * 
     * @uxon-property image_url_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return CommentsFeed
     */
    public function setImageUrlAttributeAlias(string $value) : CommentsFeed
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
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentCreatedDateColumn() : DataColumn
    {
        if ($this->commentCreatedDateAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentCreatedDateColumn !== null) {
            return $this->commentCreatedDateColumn;
        }
        throw new WidgetConfigurationError($this, 'No data column with created date found!');
    }
    
    public function hasCommentCreatedDateColumn() : bool
    {
        if ($this->commentCreatedDateAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentCreatedDateAttributeAlias !== null;
    }
    
    /**
     * 
     * @uxon-property comment_created_date_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentCreatedDateAttributeAlias(string $value) : CommentsFeed
    {
        $this->commentCreatedDateAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentCreatedDateColumn = $col;
        return $this;
    }
    /**
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentEditedDateColumn() : DataColumn
    {
        if ($this->commentEditedDateAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentEditedDateColumn !== null) {
            return $this->commentEditedDateColumn;
        }
        throw new WidgetConfigurationError($this, 'No data column with edited date found!');
    }
    
    public function hasCommentEditedDateColumn() : bool
    {
        if ($this->commentEditedDateAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentEditedDateAttributeAlias !== null;
    }
    
    /**
     * 
     * @uxon-property comment_edited_date_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentEditedDateAttributeAlias(string $value) : CommentsFeed
    {
        $this->commentEditedDateAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentEditedDateColumn = $col;
        return $this;
    }

    /**
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentIdColumn() : DataColumn
    {
        if ($this->commentIdAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentIdColumn !== null) {
            return $this->commentIdColumn;
        }
        throw new WidgetConfigurationError($this, 'No data column with comment id found!');
    }
    
    public function hasCommentIdColumn() : bool
    {
        if ($this->commentIdAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentIdAttributeAlias !== null;
    }
    
    /**
     * 
     * @uxon-property comment_id_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentIdAttributeAlias(string $value) : CommentsFeed
    {
        $this->commentIdAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentIdColumn = $col;
        return $this;
    }

    /**
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentAuthorIdColumn() : DataColumn
    {
        if ($this->commentAuthorIdAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentAuthorIdColumn !== null) {
            return $this->commentAuthorIdColumn;
        }
        throw new WidgetConfigurationError($this, 'No data column with comment id found!');
    }
    
    public function hasCommentAuthorIdColumn() : bool
    {
        if ($this->commentAuthorIdAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentAuthorIdAttributeAlias !== null;
    }
    
    /**
     * 
     * @uxon-property comment_author_id_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentAuthorIdAttributeAlias(string $value) : CommentsFeed
    {
        $this->commentAuthorIdAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentAuthorIdColumn = $col;

        $col2 = $this->createColumnFromUxon(new UxonObject(["Calculation"=>"=Calc(User('UID') == $value ? true : false)"]));
        $this->addColumn($col2);
        $this->isCurrentUserAuthor = $col2;

        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getCommentAuthorColumn() : DataColumn
    {
        return $this->commentAuthorColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasCommentAuthorColumn() : bool
    {
        return $this->commentAuthorAttributeAlias !== null;
    }

    /**
     * 
     * @return DataColumn
     */
    public function getIsCurrentUserAuthor() : DataColumn
    {
        return $this->isCurrentUserAuthor;
    }

    /**
     * 
     * 
     * @uxon-property comment_author_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentAuthorAttributeAlias(string $value) : CommentsFeed
    {
        $this->commentAuthorAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentAuthorColumn = $col;
        return $this;
    }

    /**
     *
     * @return ?bool
     */
    public function isCurrentUserAdmin() : ?bool
    {
        return $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getRoles() === "Comment Admin Role";
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
     * @return CommentsFeed
     */
    public function setOrientation(?string $value) : CommentsFeed
    {
        $value = trim(strtolower($value));
        
        if ($value !== self::ORIENTATION_HORIZONTAL && $value !== self::ORIENTATION_VERTICAL) {
            throw new WidgetConfigurationError($this, 'Invalid CommentsFeed orientation "' . $value . '": only "vertical" or "horizontal" are allowed!');
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
     * @return CommentsFeed
     */
    public function setUploadEnabled(bool $value) : CommentsFeed
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
     * @return CommentsFeed
     */
    public function setUploader(UxonObject $value) : CommentsFeed
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
            $uploader = $this->getUploader();
            if ($uploader->isInstantUpload() === true) {
                yield $this->getUploader()->getInstantUploadButton();
            }
            if ($this->getUploader()->hasUploadEditPopup() === true) {
                yield $uploader->getUploadEditPopup();
            }
        }
        
        return;
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
     * In an CommentsFeed readonly means it cannot upload, so there is no point in an
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
     * An CommentsFeed is readonly if it does not do upload as part of form data.
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
        if ($width !== null && $height !== null) {
            $url = HttpFileServerFacade::buildUrlToThumbnail($this->getMetaObject(), $uid, $width, $height, false, $relativeToSiteRoot);
        } else {
            $url = HttpFileServerFacade::buildUrlToViewData($this->getMetaObject(), $uid, false, $relativeToSiteRoot);
        }
        return $url;
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
     * @return CommentsFeed
     */
    public function setDownloadEnabled(bool $value) : CommentsFeed
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
     * @return CommentsFeed
     */
    public function setFilenameAttributeAlias(string $value) : CommentsFeed
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
                $this->setFilenameAttributeAlias($attr->getAliasWithRelationPath());
            }
            
            if ($this->commentTitleColumn === null && $attr = $behavior->getFilenameAttribute()) {
                $this->setCommentTitleAttributeAlias($attr->getAliasWithRelationPath());
            }
            
            if ($this->commentCreatedDateColumn === null && $attr = $behavior->getMimeTypeAttribute()) {
                $this->setCommentCreatedDateAttributeAlias($attr->getAliasWithRelationPath());
            }

            if ($this->commentEditedDateColumn === null && $attr = $behavior->getMimeTypeAttribute()) {
                $this->setCommentEditedDateAttributeAlias($attr->getAliasWithRelationPath());
            }

            if ($this->commentIdColumn === null && $attr = $behavior->getMimeTypeAttribute()) {
                $this->setCommentIdAttributeAlias($attr->getAliasWithRelationPath());
            }

            if ($this->commentAuthorIdColumn === null && $attr = $behavior->getMimeTypeAttribute()) {
                $this->setCommentAuthorIdAttributeAlias($attr->getAliasWithRelationPath());
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
    }
}