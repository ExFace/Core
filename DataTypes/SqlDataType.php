<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\RuntimeException;

/**
 * SQL statements
 */
class SqlDataType extends CodeDataType
{
    /**
     * @param string $sql
     * @param string[] $dialects
     * @return string
     */
    public static function findSqlDialect(string $sql, array $dialects) : string
    {
        if (StringDataType::startsWith($sql, '@')) {
            $stmts = preg_split('/(^|\R)@/', $sql);
            $tags = $dialects;
            // Start with the first supported tag and see if it matches any statement. If not,
            // proceed with the next tag, etc.
            foreach ($tags as $tag) {
                $tag = $tag . ':';
                foreach ($stmts as $stmt) {
                    if (StringDataType::startsWith($stmt, $tag, false)) {
                        return trim(StringDataType::substringAfter($stmt, $tag));
                    }
                }
            }
            // If no tag matched, throw an error!
            throw new RuntimeException('Multi-dialect SQL data address "' . StringDataType::truncate($sql, 50, false, true, true) . '" does not contain a statement for with any of the supported dialect-tags: `@' . implode(':`, `@', $dialects) . ':`', '7DGRY8R');
        }
        return $sql;
    }
}