<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\DataQueries\PhpAnnotationsDataQuery;
use Wingu\OctopusCore\Reflection\ReflectionMethod;
use Wingu\OctopusCore\Reflection\ReflectionClass;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\QueryBuilderException;
use Wingu\OctopusCore\Reflection\ReflectionDocComment;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use Wingu\OctopusCore\Reflection\ReflectionProperty;
use Wingu\OctopusCore\Reflection\ReflectionConstant;

/**
 * A query builder to read annotations for PHP classes, their methods, properties and constants.
 * 
 * Reads general comments and any specified annotation tags. See meta objects
 * `UXON_ENTITY_ANNOTATION` and `UXON_PROPERTY_ANNOTATION` of the Core for examples.
 * 
 * The type/level of annotations to read can be specified in the data address property
 * `annotation_level`.
 *
 * @author Andrej Kabachnik
 *        
 */
class PhpAnnotationsReader extends AbstractQueryBuilder
{
    /**
     * Which level of annotation to use: class, method, property or constant
     * 
     * @uxon-property annotation_level
     * @uxon-target object
     * @uxon-type [class|method|property|constant|all]
     * @uxon-default all
     * 
     * @var string
     */
    const DS_ANNOTATION_LEVEL = 'annotation_level';
    
    /**
     * Set to TRUE to include empty data rows for comments without any of the specified annotation tags.
     * 
     * @uxon-property ignore_comments_without_matching_tags
     * @uxon-target object
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @var string
     */
    const DS_IGNORE_COMMENTS_WITHOUT_MATCHING_TAGS = 'ignore_comments_without_matching_tags';

    const ANNOTATION_LEVEL_METHOD = 'method';

    const ANNOTATION_LEVEL_CLASS = 'class';

    const ANNOTATION_LEVEL_PROPERTY = 'property';
    
    const ANNOTATION_LEVEL_CONSTANT = 'constant';
    
    const COMMENT_TRIM_LINE_PATTERN = "\n\r\0\x0B";
    
    private $last_query = null;

    /**
     *
     * @return PhpAnnotationsDataQuery
     */
    protected function buildQuery()
    {
        $query = new PhpAnnotationsDataQuery();
        $query->setBasePath($this->getWorkbench()->filemanager()->getPathToVendorFolder());
        
        // Look for filters, that can be processed by the connector itself
        foreach ($this->getFilters()->getFilters() as $qpart) {
            switch (mb_strtolower($qpart->getAttribute()->getDataAddress())) {
                case 'filename-relative':
                    $query->setPathRelative($qpart->getCompareValue());
                    break;
                case 'filename':
                    $query->setPathAbsolute($qpart->getCompareValue());
                    break;
                case 'class':
                    $query->setClassNameWithNamespace($qpart->getCompareValue());
                    break;
                case 'fqsen':
                    $class_name = substr($qpart->getCompareValue(), 0, strpos($qpart->getCompareValue(), '::'));
                    if (strpos($class_name, '\\') !== 0) {
                        $class_name = '\\' . $class_name;
                    }
                    $query->setClassNameWithNamespace($class_name);
                // No break; here because we only use the beginning of the value, so the part after :: should be filtered after reading
                default:
                    $qpart->setApplyAfterReading(true);
            }
        }
        
        // All the sorting must be done locally
        foreach ($this->getSorters() as $qpart) {
            $qpart->setApplyAfterReading(true);
        }
        
        return $query;
    }

    protected function getAnnotationLevel()
    {
        return strtolower($this->getMainObject()->getDataAddressProperty(static::DS_ANNOTATION_LEVEL));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result_rows = array();
        $annotation_level = $this->getAnnotationLevel();
        
        // Check if force filtering is enabled
        if (count($this->getFilters()->getFiltersAndNestedGroups()) < 1) {
            throw new QueryBuilderException('Cannot use query builder PhpAnnotationReader without filters: no files to read!');
        }
        
        $query = $data_connection->query($this->buildQuery());
        $this->setLastQuery($query);
        if ($className = $query->getClassNameWithNamespace()) {
            $class = new ReflectionClass($className);
            // Read class annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_CLASS) {
                $row = $this->buildRowFromClass($class, array());
                if (count($row) > 0) {
                    $result_rows[] = $row;
                }
            }
            
            // Read method annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_METHOD) {
                foreach ($class->getMethods() as $method) {
                    $row = $this->buildRowFromMethod($class, $method, array());
                    if (count($row) > 0) {
                        $result_rows[] = $row;
                    }
                }
            }
            
            // Read property annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_PROPERTY) {
                foreach ($class->getProperties() as $property) {
                    $row = $this->buildRowFromProperty($class, $property, array());
                    if (count($row) > 0) {
                        $result_rows[] = $row;
                    }
                }
            }
            
            // Read constant annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_CONSTANT) {
                // For some reason, constants without comments get the comment from the previous
                // constant, so need to filter away these duplicates by simply comparing the
                // UXON property with the previous one.
                $prevRow = [];
                foreach ($class->getConstants() as $constant) {
                    $row = $this->buildRowFromConstant($class, $constant, array());
                    if (! empty($prevRow) && $prevRow['PROPERTY'] === $row['PROPERTY']) {
                        continue;
                    }
                    if (count($row) > 0) {
                        $result_rows[] = $row;
                        $prevRow = $row;
                    }
                }
            }
            
            $result_rows = $this->applyFilters($result_rows);
            $resultTotalRowCounter = count($result_rows);
            $result_rows = $this->applySorting($result_rows);
            $result_rows = $this->applyPagination($result_rows);
        }
        
        $rowCnt = count($result_rows);
        if (! $resultTotalRowCounter) {
            $resultTotalRowCounter = $rowCnt;
        }
        
        return new DataQueryResultData($result_rows, $rowCnt, ($resultTotalRowCounter > $rowCnt + $this->getOffset()), $resultTotalRowCounter);
    }

    /**
     *
     * @param \ReflectionClass $class            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromClass(\ReflectionClass $class, array $row)
    {
        $file_pathname_absolute = $this->getFilePathnameAbsolute($class);
        $file_pathname_relative = $this->getFilePathnameRelative($class);
        
        foreach ($this->getAttributesMissingInRow($row) as $qpart) {
            if (! $qpart->getDataAddress())
                continue;
            if (! array_key_exists($qpart->getColumnKey(), $row)) {
                // First fill in the fields, any annotation row will need to know about it's class
                switch ($qpart->getDataAddress()) {
                    case 'class':
                        $row[$qpart->getColumnKey()] = $class->getName();
                        break;
                    case 'classname':
                        $row[$qpart->getColumnKey()] = str_replace($class->getNamespaceName().'\\', '', $class->getName());
                        break;
                    case 'namespace':
                        $row[$qpart->getColumnKey()] = $class->getNamespaceName();
                        break;
                    case 'filename':
                        $row[$qpart->getColumnKey()] = $file_pathname_absolute;
                        break;
                    case 'filename-relative':
                        $row[$qpart->getColumnKey()] = $file_pathname_relative;
                        break;
                }
                
                // If we are specificlally interesten in the class annotations, search for fields
                // in the class comment specifically
                if ($this->getAnnotationLevel() == $this::ANNOTATION_LEVEL_CLASS) {
                    if ($comment = $class->getReflectionDocComment(self::COMMENT_TRIM_LINE_PATTERN)) {
                        $row = $this->buildRowFromCommentTags($class, $comment, $row);
                        $row = $this->buildRowFromComment($class, $comment, $row);
                    }
                    // Add the FQSEN (Fully Qualified Structural Element Name) if we are on class level
                    foreach ($this->getAttributesMissingInRow($row) as $qpart) {
                        if (strcasecmp($qpart->getDataAddress(), 'FQSEN') === 0) {
                            $row[$qpart->getColumnKey()] = $class->getName();
                        }
                    }
                }
            }
        }
        return $row;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @param ReflectionDocComment $comment            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromCommentTags(ReflectionClass $class, ReflectionDocComment $comment, $row)
    {
        // Loop through all attributes to find exactly matching annotations
        foreach ($this->getAttributesMissingInRow($row) as $qpart) {
            // Only process attributes with data addresses
            if (! $qpart->getDataAddress())
                continue;
            // Do not overwrite already existent values (could happen when processing a parent class)
            if (array_key_exists($qpart->getColumnKey(), $row))
                continue;
            
            // First look through the real tags for exact matches
            try {
                $delim = $qpart->getAttribute()->getValueListDelimiter();
                $colKey = $qpart->getColumnKey();
                $colVal = null;
                foreach ($comment->getAnnotationsCollection()->getAnnotations() as $tag) {
                    if ($tag->getTagName() == $qpart->getDataAddress()) {
                        $colVal = ($colVal !== null ? $colVal . $delim : '') . $tag->getDescription();
                    }
                }
                if ($colVal !== null) {
                    $row[$colKey] = $colVal;
                }
            } catch (\Exception $e) {
                throw new DataQueryFailedError($this->getLastQuery(), 'Cannot read annotation "' . $comment->getOriginalDocBlock() . '": ' . $e->getMessage(), null, $e);
            } catch (\ErrorException $e) {
                throw new DataQueryFailedError($this->getLastQuery(), 'Cannot read annotation "' . $comment->getOriginalDocBlock() . '": ' . $e->getMessage(), null, $e);
            }
        }
        return $row;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @param ReflectionMethod $property            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromMethod(ReflectionClass $class, ReflectionMethod $method, array $row)
    {
        // First look for exact matches among the tags within the comment
        $comment = $method->getReflectionDocComment(self::COMMENT_TRIM_LINE_PATTERN);
        $row = $this->buildRowFromCommentTags($class, $comment, $row);
        
        // If at least one exact match was found, this method is a valid row.
        // Now add enrich the row with general comment fields (description, etc.) and fields from the class level
        if (! $this->getIgnoreCommentsWithoutMatchingTags($this->getMainObject()) || count($row) > 0) {
            $row = $this->buildRowFromClass($class, $row);
            $row = $this->buildRowFromComment($class, $comment, $row);
            // Add the FQSEN (Fully Qualified Structural Element Name) if we are on method level
            foreach ($this->getAttributesMissingInRow($row) as $qpart) {
                if (strcasecmp($qpart->getDataAddress(), 'fqsen') === 0) {
                    $row[$qpart->getColumnKey()] = $class->getName() . '::' . $method->getName() . '()';
                }
            }
        }
        
        return $row;
    }
    
    /**
     *
     * @param ReflectionClass $class
     * @param ReflectionProperty $property
     * @param array $row
     * @return string
     */
    protected function buildRowFromProperty(ReflectionClass $class, ReflectionProperty $property, array $row)
    {
        // First look for exact matches among the tags within the comment
        $comment = $property->getReflectionDocComment(self::COMMENT_TRIM_LINE_PATTERN);
        $row = $this->buildRowFromCommentTags($class, $comment, $row);
        
        // If at least one exact match was found, this method is a valid row.
        // Now add enrich the row with general comment fields (description, etc.) and fields from the class level
        if (! $this->getIgnoreCommentsWithoutMatchingTags($this->getMainObject()) || count($row) > 0) {
            $row = $this->buildRowFromClass($class, $row);
            $row = $this->buildRowFromComment($class, $comment, $row);
            // Add the FQSEN (Fully Qualified Structural Element Name) if we are on property level
            foreach ($this->getAttributesMissingInRow($row) as $qpart) {
                if (strcasecmp($qpart->getDataAddress(), 'fqsen') === 0) {
                    $row[$qpart->getColumnKey()] = $class->getName() . '::' . $property->getName();
                }
            }
        }
        
        return $row;
    }
    
    /**
     *
     * @param ReflectionClass $class
     * @param ReflectionConstant $constant
     * @param array $row
     * @return string
     */
    protected function buildRowFromConstant(ReflectionClass $class, ReflectionConstant $constant, array $row)
    {
        // First look for exact matches among the tags within the comment
        $comment = $constant->getReflectionDocComment(self::COMMENT_TRIM_LINE_PATTERN);
        $row = $this->buildRowFromCommentTags($class, $comment, $row);
        
        // If at least one exact match was found, this method is a valid row.
        // Now add enrich the row with general comment fields (description, etc.) and fields from the class level
        if (! $this->getIgnoreCommentsWithoutMatchingTags($this->getMainObject()) || count($row) > 0) {
            $row = $this->buildRowFromClass($class, $row);
            $row = $this->buildRowFromComment($class, $comment, $row);
            // Add the FQSEN (Fully Qualified Structural Element Name) if we are on const level
            foreach ($this->getAttributesMissingInRow($row) as $qpart) {
                if (strcasecmp($qpart->getDataAddress(), 'fqsen') === 0) {
                    $row[$qpart->getColumnKey()] = $class->getName() . '::' . $constant->getName();
                }
            }
        }
        
        return $row;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @param ReflectionDocComment $comment            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromComment(ReflectionClass $class, ReflectionDocComment $comment, array $row)
    {
        foreach ($this->getAttributesMissingInRow($row) as $qpart) {
            if (! array_key_exists($qpart->getColumnKey(), $row)) {
                switch ($qpart->getDataAddress()) {
                    case 'desc':
                        $row[$qpart->getColumnKey()] = $this->prepareCommentText($comment->getFullDescription());
                        break;
                    case 'desc-short':
                        $row[$qpart->getColumnKey()] = $this->prepareCommentText($comment->getShortDescription());
                        break;
                    case 'desc-long':
                        $row[$qpart->getColumnKey()] = $this->prepareCommentText($comment->getLongDescription());
                        break;
                }
            }
        }
        return $row;
    }

    /**
     * Removes single line breaks while leaving empty lines.
     *
     * @param string $string            
     * @return string
     */
    protected function prepareCommentText($string)
    {
        $string = preg_replace('/([^\r\n])\R([^{}\s\r\n#=-])/', '$1 $2', $string);
        return $string;
    }

    /**
     *
     * @param array $row            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute[]
     */
    protected function getAttributesMissingInRow(array $row)
    {
        $result = array();
        foreach ($this->getAttributes() as $qpart) {
            // Only process attributes with data addresses
            if (! $qpart->getDataAddress())
                continue;
            // Do not overwrite already existent values (could happen when processing a parent class)
            if (array_key_exists($qpart->getColumnKey(), $row))
                continue;
            // Otherwise add the query part to the resulting array
            $result[] = $qpart;
        }
        return $result;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @return string
     */
    protected function getFilePathnameRelative(ReflectionClass $class)
    {
        return Filemanager::pathNormalize(str_replace($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR, '', $class->getFileName()));
    }

    /**
     *
     * @param ReflectionClass $class            
     * @return string
     */
    protected function getFilePathnameAbsolute(ReflectionClass $class)
    {
        return Filemanager::pathNormalize($class->getFileName());
    }

    /**
     *
     * @return boolean
     */
    protected function getIgnoreCommentsWithoutMatchingTags(MetaObjectInterface $object) : bool
    {
        return BooleanDataType::cast($object->getDataAddressProperty(self::DS_IGNORE_COMMENTS_WITHOUT_MATCHING_TAGS));
    }

    protected function getLastQuery()
    {
        return $this->last_query;
    }

    protected function setLastQuery(PhpAnnotationsDataQuery $value)
    {
        $this->last_query = $value;
        return $this;
    }
    
    /**
     * The PhpAnnotationsReader can only handle attributes of one object - no relations (JOINs) supported!
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $attribute->getRelationPath()->isEmpty();
    }
}
?>