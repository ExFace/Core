<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\SemanticVersionDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataTypeFactory;

/**
 * Increases the semantic version number by configurable major, minor, and patch deltas.
 *
 * The formula expects a semantic version in the format `MAJOR.MINOR.PATCH` and supports
 * optional pre-release and build metadata suffixes (e.g. `1.2.3-beta.1+build.5`).
 *
 * ## Examples
 *
 * - `=VersionIncrease('1.2.3', 0, 0, 1)` -> `1.2.4`
 * - `=VersionIncrease('1.2.3', 1, 0, 0)` -> `2.2.3`
 * - `=VersionIncrease('1.2.3-beta.1', 0, 1, 0)` -> `1.3.3-beta.1`
 * - `=VersionIncrease('1.2.3', -1, 0, 0)` -> `0.2.3`
 * - `=VersionIncrease('1.03.01', 0, 0, 1)` -> `1.03.02`
 * - `=VersionIncrease('1.09.09', 0, 1, 1)` -> `1.10.10`
 *
 * Version parts retain at least their original number of digits. They grow when needed,
 * as shown when `09` becomes `10` in the examples above.
 *
 * @author Andrej Kabachnik
 */
class VersionIncrease extends Formula
{
    /**
     * Increases the semantic version number parts by the given deltas.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $version = null, $major = 0, $minor = 0, $patch = 0)
    {
        if ($version === null || $version === '') {
            return '';
        }

        try {
            return SemanticVersionDataType::increment($version, $major, $minor, $patch);
        } catch (\Throwable $e) {
            throw new FormulaError($this, 'Cannot evaluate formula =VersionIncrease(): ' . $e->getMessage(), null, $e, [$version, $major, $minor, $patch]);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
    }
}
