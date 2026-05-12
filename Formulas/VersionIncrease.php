<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
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
            $parts = $this->parseSemVer($version);
            $majorDelta = $this->parseDelta($major, 'major');
            $minorDelta = $this->parseDelta($minor, 'minor');
            $patchDelta = $this->parseDelta($patch, 'patch');

            $newMajor = $parts['major'] + $majorDelta;
            $newMinor = $parts['minor'] + $minorDelta;
            $newPatch = $parts['patch'] + $patchDelta;

            if ($newMajor < 0 || $newMinor < 0 || $newPatch < 0) {
                throw new FormulaError($this, 'Cannot evaluate formula =VersionIncrease(): resulting semantic version parts must not be negative.');
            }

            return $newMajor . '.' . $newMinor . '.' . $newPatch . $parts['suffix'];
        } catch (FormulaError $e) {
            throw $e;
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

    /**
     * Parses a semantic version into numeric parts and optional suffix.
     *
     * @param string $version
     * @return array{major:int,minor:int,patch:int,suffix:string}
     */
    protected function parseSemVer(string $version) : array
    {
        $pattern = '/^(0|[1-9]\\d*)\.(0|[1-9]\\d*)\.(0|[1-9]\\d*)((?:-[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?(?:\\+[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?)$/';

        if (preg_match($pattern, $version, $matches) !== 1) {
            throw new FormulaError($this, 'Cannot evaluate formula =VersionIncrease(): invalid semantic version "' . $version . '" provided.');
        }

        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'patch' => (int) $matches[3],
            'suffix' => $matches[4] ?? ''
        ];
    }

    /**
     * Parses a major/minor/patch delta as integer.
     *
     * @param mixed $value
     * @param string $name
     * @return int
     */
    protected function parseDelta($value, string $name) : int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (preg_match('/^-?\\d+$/', (string) $value) !== 1) {
            throw new FormulaError($this, 'Cannot evaluate formula =VersionIncrease(): invalid ' . $name . ' delta "' . $value . '" provided.');
        }

        return (int) $value;
    }
}