<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;

/**
 * Makes easy to manage comments widgets with the same table structure.
 * 
 * 
 * * ## Examples
 * 
 * ### Simple comments
 * 
 * ```
 * {
 *    "comment_id_attribute": "UID",
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
class CommentBehavior extends AbstractBehavior
{    
    private $commentContentAttributeAlias = null;
    
    private $commentCreatedDateAttributeAlias = null;
    
    private $commentEditedDateAttributeAlias = null;
    
    private $commentAuthorAttributeAlias = null;
    
    private $commentIdAttributeAlias = null;
    
    private $commentAuthorIdAttributeAlias = null;
    
    private $timeModifiedAttributeAlias = null;
    
    private $allowedFileExtensions = [];
    
    private $allowedMimeTypes = [];
    
    private $maxFilenameLength = 255;
    
    private $maxFileSizeMb = null;

    private $imageResizeToMaxSide = null;

    private $imageResizeQuality = null;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getCommentCreatedDateAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->commentCreatedDateAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the date of creation
     * 
     * @uxon-property comment_created_date_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return CommentBehavior
     */
    protected function setCommentCreatedDateAttribute(string $value) : CommentBehavior
    {
        $this->commentCreatedDateAttributeAlias = $value;
        return $this;
    }

    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getCommentContentAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->commentContentAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the comment as a text
     * 
     * @uxon-property comment_content_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return CommentBehavior
     */
    protected function setCommentContentAttribute(string $value) : CommentBehavior
    {
        $this->commentContentAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getCommentEditedDateAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->commentEditedDateAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the date of modification
     * 
     * @uxon-property comment_edited_date_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return CommentBehavior
     */
    protected function setCommentEditedDateAttribute(string $value) : CommentBehavior
    {
        $this->commentEditedDateAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getCommentAuthorAttribute() : ?MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->commentAuthorAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the Author Full Name
     *
     * @uxon-property comment_author_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return CommentBehavior
     */
    protected function setCommentAuthorAttribute(string $value) : CommentBehavior
    {
        $this->commentAuthorAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getCommentIdAttribute() : ?MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->commentIdAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the UID of the comment
     *
     * @uxon-property comment_id_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return CommentBehavior
     */
    protected function setCommentIdAttribute(string $value) : CommentBehavior
    {
        $this->commentIdAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getCommentAuthorIdAttribute() : ?MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->commentAuthorIdAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the UID of the author
     *
     * @uxon-property comment_author_id_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return CommentBehavior
     */
    protected function setCommentAuthorIdAttribute(string $value) : CommentBehavior
    {
        $this->commentAuthorIdAttributeAlias = $value;
        return $this;
    }
    
    
    /**
     * 
     * @return array<MetaAttributeInterface|null>
     */
    public function getFileAttributes() : array
    {
        $attrs = [];
        if (null !== $attr = $this->getCommentIdAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getCommentCreatedDateAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getCommentContentAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getCommentEditedDateAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getCommentAuthorIdAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getCommentAuthorAttribute()) {
            $attrs[] = $attr;
        }
        return $attrs;
    }
}