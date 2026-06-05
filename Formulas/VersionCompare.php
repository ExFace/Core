<?php
namespace exface\Core\Formulas;

use Composer\Semver\Comparator;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\SemanticVersionDataType;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataTypeFactory;

/**
 * Compares two semantic version numbers.
 *
 * The formula expects semantic versions in the format `MAJOR.MINOR.PATCH` and supports
 * optional pre-release and build metadata suffixes (e.g. `1.2.3-beta.1+build.5`).
 *
 * The return value is compatible with typical compare functions:
 *
 * - `-1` if version1 < version2
 * - `0` if version1 = version2
 * - `1` if version1 > version2
 *
 * Comparison follows semantic version precedence rules implemented centrally in
 * `SemanticVersionDataType` / Composer Semver:
 *
 * - major, minor and patch are compared numerically
 * - a version without pre-release has higher precedence than one with pre-release
 * - pre-release identifiers are compared according to SemVer rules
 * - build metadata is ignored for precedence
 *
 * ## Examples
 *
 * - `=VersionCompare('1.2.3', '1.2.4')` -> `-1`
 * - `=VersionCompare('1.2.3', '1.2.3')` -> `0`
 * - `=VersionCompare('2.0.0', '1.9.9')` -> `1`
 * - `=VersionCompare('1.0.0-alpha', '1.0.0')` -> `-1`
 * - `=VersionCompare('1.0.0-alpha.1', '1.0.0-alpha.beta')` -> `-1`
 * - `=VersionCompare('1.0.0+build.1', '1.0.0+build.2')` -> `0`
 *
 * @author Andrej Kabachnik
 */
class VersionCompare extends Formula
{
    /**
     * Compares two semantic versions.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $version1 = null, string $version2 = null)
    {
        if (($version1 === null || $version1 === '') && ($version2 === null || $version2 === '')) {
            return 0;
        }

        if ($version1 === null || $version1 === '' || $version2 === null || $version2 === '') {
            throw new FormulaError($this, 'Cannot evaluate formula =VersionCompare(): both semantic versions must be provided.');
        }

        try {
            $this->validateVersion($version1);
            $this->validateVersion($version2);

            if (Comparator::lessThan($version1, $version2)) {
                return -1;
            }

            if (Comparator::greaterThan($version1, $version2)) {
                return 1;
            }

            return 0;
        } catch (FormulaError $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FormulaError($this, 'Cannot evaluate formula =VersionCompare(): ' . $e->getMessage(), null, $e, [$version1, $version2]);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), NumberDataType::class);
    }

    /**
     * Validates the given semantic version using the centralized data type logic.
     *
     * @param string $version
     * @return void
     */
    protected function validateVersion(string $version) : void
    {
        if (SemanticVersionDataType::isValueVersion($version) === false) {
            throw new FormulaError($this, 'Cannot evaluate formula =VersionCompare(): invalid semantic version "' . $version . '" provided.');
        }
    }
}
