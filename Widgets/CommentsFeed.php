<?php
namespace exface\Core\Widgets;

use exface\Core\Behaviors\CommentBehavior;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Widgets\Parts\Uploader;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Traits\EditableTableTrait;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iCanEditData;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;

/**
 * Shows a feed of comments (as seen in blogs or news pages) with an option of quickly adding a comment
 * 
 * This widget simplifies building good comment UX. It includes actions to create, edit and delete comments
 * automatically. Users can edit their own comments any time, but can only view other peoples comments. You can define
 * `comment_moderator_roles` to make users with these roles act as moderators and allow them to edit or delete other 
 * peoples comments too. 
 * 
 * The `CommentsFeed` need to know where to get certain information from: e.g. the comment body, the author, creation
 * times, etc. It is recommended to give the object, that stores comments, a `CommentBehavior`. This will automatically
 * allow all comment widgets to understand, how to deal with in. Alternatively, you can specify `comment_` attributes
 * in this widget manually.
 * 
 * You can also use a regular `DataTable` for comments and configure all that logic manually, but a special comments
 * widget simplifies this a lot!
 * 
 * ## Examples
 * 
 * ### Comment feed with an object with `CommentBehavior`
 * 
 * If you have a dedicated comment object, give it the `CommentBehavior` to tell the workbench, that it is a comment.
 * All comment-related widget will then work with minimal configuration!
 * 
 * ```
 *  {
 *     "widget_type": "CommentsFeed",
 *     "object_alias": "my.App.COMMENTS",
 *  }
 * 
 * ```
 * 
 * ### Comment feed with custom-defined attributes
 * 
 * If there is no `CommentBehavior` on the object of the widget, you will need to specify `comment_` attributes manually.
 * 
 * ```
 * {
 *    "widget_type": "CommentsFeed",
 *    "object_alias": "my.App.COMMENTS",
 *    "comment_created_date_attribute": "CREATED_ON",
 *    "comment_edited_date_attribute": "MODIFY_ON",
 *    "comment_author_id_attribute": "CREATED_BY",
 *    "comment_author_attribute": "CREATED_BY__FULL_NAME",
 *    "comment_content_attribute": "TITLE",
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
    
    private ?DataColumn $commentContentColumn = null;
    private ?string $commentContentAttributeAlias = null;
    private ?DataColumn $commentCreatedDateColumn = null;
    private ?string $commentCreatedDateAttributeAlias = null;
    private ?DataColumn $commentEditedDateColumn = null;
    private ?string $commentEditedDateAttributeAlias = null;
    private ?DataColumn $commentAuthorColumn = null;
    private ?string $commentAuthorAttributeAlias = null;
    private ?DataColumn $commentAvatarUrlColumn = null;
    private ?string $commentAvatarUrlAttributeAlias = null;
    private ?DataColumn $commentIdColumn = null;
    private ?string $commentIdAttributeAlias = null;
    private ?DataColumn $commentAuthorIdColumn = null;
    private ?string $commentAuthorIdAttributeAlias = null;
    private ?DataColumn $commentAuthorIsCurrentUserColumn = null;
    private array $commentModeratorRoles = [
        'exface.Core.SUPERUSER'
    ];
    
    private ?Uploader $uploader = null;
    private ?UxonObject $uploaderUxon = null;
    private bool $uploadEnabled = false;
    
    private ?MetaObjectInterface $checkedBehaviorForObject = null;

    private ?DataButton $btnCreate = null;
    private ?DataButton $btnEdit = null;
    private ?DataButton $btnDelete = null;

    protected function init()
    {
        parent::init();
        // Comments have no headers or footer by default, but they can be
        // explicitly enabled by the user.
        $this->setHideHeader(true);
        $this->setHideFooter(true);
        return;
    }

    /**
     * Return the create action with a stable ID independent of facade toolbar grouping.
     */
    public function getButtonCreate() : DataButton
    {
        if ($this->btnCreate === null) {
            $this->btnCreate = $this->createButton(new UxonObject([
                'id' => $this->getId(false) . '_create',
                'widget_type' => 'DataButton',
                'visibility' => 'hidden',
                'action_alias' => 'exface.Core.CreateData'
            ]));
            $this->addButton($this->btnCreate);
        }
        return $this->btnCreate;
    }

    /**
     * Return the delete action with a stable ID independent of facade toolbar grouping.
     */
    public function getButtonDelete() : DataButton
    {
        if ($this->btnDelete === null) {
            $this->btnDelete = $this->createButton(new UxonObject([
                'id' => $this->getId(false) . '_delete',
                'widget_type' => 'DataButton',
                'visibility' => 'hidden',
                'action_alias' => 'exface.Core.DeleteObject'
            ]));
            $this->addButton($this->btnDelete);
        }
        return $this->btnDelete;
    }

    /**
     * Return the update action with a stable ID independent of facade toolbar grouping.
     */
    public function getButtonEdit() : DataButton
    {
        if ($this->btnEdit === null) {
            $this->btnEdit = $this->createButton(new UxonObject([
                'id' => $this->getId(false) . '_edit',
                'widget_type' => 'DataButton',
                'visibility' => 'hidden',
                'action_alias' => 'exface.Core.UpdateData'
            ]));
            $this->addButton($this->btnEdit);
        }
        return $this->btnEdit;
    }

    /**
     * 
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentContentColumn() : DataColumn
    {
        if ($this->commentContentAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentContentColumn !== null) {
            return $this->commentContentColumn;
        } 
        throw new WidgetConfigurationError($this, 'No data column to be used for comment contents could be found!');
    }
    
    /**
     * 
     * @return bool
     */
    public function hasCommentContentColumn() : bool
    {
        if ($this->commentContentAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentContentAttributeAlias !== null;
    }
    
    /**
     * The attribute containing the text of each comment.
     * 
     * Required unless the object has a `CommentBehavior` with a comment content attribute.
     * 
     * @uxon-property comment_content_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentContentAttribute(string $value) : CommentsFeed
    {
        $this->commentContentAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentContentColumn = $col;
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
     * Attribute containing the creation time of each comment.
     * 
     * Required unless the object has a `CommentBehavior` with a comment created date attribute.
     * 
     * @uxon-property comment_created_date_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentCreatedDateAttribute(string $value) : CommentsFeed
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
     * Attribute containing the edited time of each comment.
     * 
     * Required unless the object has a `CommentBehavior` with a comment edited date attribute.
     * 
     * @uxon-property comment_edited_date_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentEditedDateAttribute(string $value) : CommentsFeed
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
     * Attribute containing the unique ID of each comment.
     * 
     * By default, the widget will use the UID attribute of the object if it has one. Only override this property if
     * comments really need some custom UIDs - e.g. public ids. That should be very rare.
     * 
     * @uxon-property comment_id_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentIdAttribute(string $value) : CommentsFeed
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
     * Attribute with the UID of each comment's author - used to determine whether the current user is the author.
     * 
     * @uxon-property comment_author_id_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentAuthorIdAttribute(string $value) : CommentsFeed
    {
        $this->commentAuthorIdAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentAuthorIdColumn = $col;

        $col2 = $this->createColumnFromUxon(new UxonObject(["Calculation"=>"=IsTrue(User('UID') == $value)"]));
        $col2->setHidden(true);
        $this->addColumn($col2);
        $this->commentAuthorIsCurrentUserColumn = $col2;

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
    public function getCommentAuthorIsCurrentUserColumn() : DataColumn
    {
        return $this->commentAuthorIsCurrentUserColumn;
    }   

    /**
     * Attribute containing the visible name of each comment's author.
     * 
     * Required unless the object has a `CommentBehavior` with a comment author attribute.
     * 
     * @uxon-property comment_author_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentAuthorAttribute(string $value) : CommentsFeed
    {
        $this->commentAuthorAttributeAlias = $value;
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentAuthorColumn = $col;
        return $this;
    }

    /**
     * Return the optional column containing comment authors' avatar URLs.
     *
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getCommentAvatarUrlColumn() : DataColumn
    {
        if ($this->commentAvatarUrlAttributeAlias === null) {
            $this->guessColumns();
        }
        if ($this->commentAvatarUrlColumn !== null) {
            return $this->commentAvatarUrlColumn;
        }
        throw new WidgetConfigurationError($this, 'No comment avatar URL attribute configured!');
    }

    /**
     * Return whether an avatar column is configured on the widget or its object's comment behavior.
     *
     * @return bool
     */
    public function hasCommentAvatarUrlColumn() : bool
    {
        if ($this->commentAvatarUrlAttributeAlias === null) {
            $this->guessColumns();
        }
        return $this->commentAvatarUrlAttributeAlias !== null;
    }

    /**
     * Attribute containing the URL of each comment author's avatar.
     * 
     * Leave this property unset to use the avatar attribute from the object's comment behavior.
     * If neither provides an avatar attribute, author avatars are hidden on existing comments.
     * 
     * @uxon-property comment_avatar_url_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return CommentsFeed
     */
    public function setCommentAvatarUrlAttribute(string $value) : CommentsFeed
    {
        $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
        $this->addColumn($col);
        $this->commentAvatarUrlAttributeAlias = $value;
        $this->commentAvatarUrlColumn = $col;
        return $this;
    }
    
    public function getCommentModeratorRoles() : array
    {
        return $this->commentModeratorRoles;
    }

    /**
     * List of user role aliases or UIDs that allow moderating comments (editing or deleting any comment, not just the user's own).
     * 
     * @uxon-property comment_moderator_roles
     * @uxon-type array
     * @uxon-template ["exface.Core.SUPERUSER"]
     * 
     * @param string[] $roles
     * @return $this
     */
    public function setCommentModeratorRoles(array $roles) : CommentsFeed
    {
        $this->commentModeratorRoles = $roles;
        return $this;
    }

    /**
     *
     * @return ?bool
     */
    public function isCurrentUserModerator() : ?bool
    {
        foreach ($this->getCommentModeratorRoles() as $role) {
            if ($this->getWorkbench()->getSecurity()->getAuthenticatedUser()->hasRole($role)) {
                return true;
            }
        }
        return false;
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
        // Register internal actions before traversing their toolbars so IDs are stable on AJAX reconstruction.
        $this->getButtonCreate();
        $this->getButtonEdit();
        $this->getButtonDelete();
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
     * Fill unspecified comment columns from the object's behavior without replacing explicit widget settings.
     */
    protected function guessColumns()
    {
        if ($this->checkedBehaviorForObject !== $this->getMetaObject()
            && ! ($behaviors = $this->getMetaObject()->getBehaviors()->getByPrototypeClass(CommentBehavior::class))->isEmpty()
        ) {
            /* @var $behavior \exface\Core\Behaviors\CommentBehavior */
            $behavior = $behaviors->getFirst();
            if ($this->commentAuthorColumn === null && $attr = $behavior->getCommentAuthorAttribute()) {
                $this->setCommentAuthorAttribute($attr->getAliasWithRelationPath());
            }

            if ($this->commentAvatarUrlColumn === null && $attr = $behavior->getCommentAvatarUrlAttribute()) {
                $this->setCommentAvatarUrlAttribute($attr->getAliasWithRelationPath());
            }
            
            if ($this->commentContentColumn === null && $attr = $behavior->getCommentContentAttribute()) {
                $this->setCommentContentAttribute($attr->getAliasWithRelationPath());
            }
            
            if ($this->commentCreatedDateColumn === null && $attr = $behavior->getCommentCreatedDateAttribute()) {
                $this->setCommentCreatedDateAttribute($attr->getAliasWithRelationPath());
            }

            if ($this->commentEditedDateColumn === null && $attr = $behavior->getCommentEditedDateAttribute()) {
                $this->setCommentEditedDateAttribute($attr->getAliasWithRelationPath());
            }

            if ($this->commentIdColumn === null && $attr = $behavior->getCommentIdAttribute()) {
                $this->setCommentIdAttribute($attr->getAliasWithRelationPath());
            }

            if ($this->commentAuthorIdColumn === null && $attr = $behavior->getCommentAuthorIdAttribute()) {
                $this->setCommentAuthorIdAttribute($attr->getAliasWithRelationPath());
            }            
        }

        // Fall back to UID attribute of object in case nothing else was specified
        if ($this->commentIdColumn === null && $this->getMetaObject()->hasUidAttribute()) {
            $this->setCommentIdAttribute($this->getMetaObject()->getUidAttributeAlias());
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
        // if ($this->isUploadEnabled()) {
        //     $cols = array_merge($cols, $this->getUploader()->getActionDataColumnNames());
        // }
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