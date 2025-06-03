<?php
namespace exface\Core\CommonLogic\Utils;

use exface\Core\Exceptions\UnexpectedValueException;
use JsonPath\JsonPath;

class JsonObject extends \JsonPath\JsonObject
{
    /**
     * Append a new value into all json arrays that match
     * the $jsonPath path.
     *
     * The $value will be inserted at the given $index moving
     * all existing elements starting from that $index further
     * to the end of the array
     *
     * @param string $jsonPath jsonPath
     * @param mixed $value value
     * @param bool $asArray
     * @param int $index will insert at this position
     *
     * @return \JsonPath\JsonObject
     */
    public function insert(string $jsonPath, $value, bool $asArray = false, ?int $index = null)
    {
        $matches = [];
        if ($index === null) {
            if (preg_match('/^(.*?)(?:\.|\[)(\d+)\]?$/', $jsonPath, $matches)) {
                $parentPath = $matches[1];
                $index = $matches[2];
            } else {
                throw new UnexpectedValueException('Cannot insert into JSON at "' . $jsonPath . '" - this path does not point to an index in an array!');
            }
        } else {
            $parentPath = $jsonPath;
        }
        list($result, $_) = JsonPath::get($this->getValue(), $parentPath, true);
        foreach ($result as &$element) {
            if (is_array($element)) {
                if ($index == null) {
                    $element[] = $value;
                } else {
                    array_splice($element, $index, 0, $asArray ? $value : [$value]);
                }
            }
        }
        return $this;
    }
}