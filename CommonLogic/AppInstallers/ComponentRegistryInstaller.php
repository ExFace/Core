<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\CommonLogic\ComponentRegistry;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * Installs app-owned component metadata into the installation's shared registry.
 */
class ComponentRegistryInstaller extends AbstractAppInstaller
{
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $source = $source_absolute_path . DIRECTORY_SEPARATOR . 'Config'
            . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME;
        $this->updateRegistry($source);
        yield $this->getOutputIndentation() . 'Installed component registry for '
            . $this->getApp()->getAliasWithNamespace() . '.' . PHP_EOL;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        $this->updateRegistry(null);
        yield $this->getOutputIndentation() . 'Uninstalled component registry for '
            . $this->getApp()->getAliasWithNamespace() . '.' . PHP_EOL;
    }

    /**
     * The app backup already contains its source fragment; the shared file is generated.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }

    /**
     * Replaces one app's contribution while holding a stable lock across atomic writes.
     *
     * @param string|null $sourceFile Null removes the app without reading its fragment.
     * @return void
     */
    private function updateRegistry(?string $sourceFile) : void
    {
        $workbench = $this->getWorkbench();
        $filemanager = $workbench->filemanager();
        $target = $filemanager->getPathToConfigFolder()
            . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME;
        $alias = $this->getApp()->getAliasWithNamespace();
        if (! file_exists($target) && ($sourceFile === null || ! file_exists($sourceFile))) {
            return;
        }

        // Keep the lock file: deleting it would let concurrent writers lock different inodes.
        $lock = fopen($target . '.lock', 'c');
        if ($lock === false) {
            throw new InstallerRuntimeError($this, 'Cannot open component registry lock "' . $target . '.lock"');
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new InstallerRuntimeError($this, 'Cannot lock component registry "' . $target . '"');
            }
            if (file_exists($target)) {
                $registry = $this->readJsonObject($target);
                $this->validateRegistry($registry, $target);
            } else {
                $coreFile = $workbench->getCoreApp()->getDirectoryAbsolutePath()
                    . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME;
                $core = $this->readJsonObject($coreFile);
                $this->validateComponents($core, $coreFile);
                $registry = (object) [
                    ComponentRegistry::CONFIG_COMPONENTS => $core,
                    ComponentRegistry::CONFIG_SOURCES => (object) ['exface.Core' => array_keys((array) $core)]
                ];
            }

            $components = $registry->{ComponentRegistry::CONFIG_COMPONENTS};
            $sources = $registry->{ComponentRegistry::CONFIG_SOURCES};
            foreach ($sources->{$alias} ?? [] as $key) {
                unset($components->{$key});
            }
            unset($sources->{$alias});

            if ($sourceFile !== null && file_exists($sourceFile)) {
                $fragment = $this->readJsonObject($sourceFile);
                $this->validateComponents($fragment, $sourceFile);
                foreach ($fragment as $key => $model) {
                    if (property_exists($components, $key)) {
                        throw new InstallerRuntimeError($this, 'Component "' . $key . '" is already owned by another app');
                    }
                    $components->{$key} = $model;
                }
                $sources->{$alias} = array_keys((array) $fragment);
            }
            $filemanager->dumpFile($target, JsonDataType::encodeJson($registry, true) . PHP_EOL);
        } catch (\Throwable $e) {
            throw new InstallerRuntimeError($this, 'Cannot update component registry "' . $target . '": ' . $e->getMessage(), null, $e);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $workbench->getComponentRegistry()->reload();
    }

    /**
     * Reads a JSON object without converting empty objects into lists.
     *
     * @param string $file
     * @return \stdClass
     */
    private function readJsonObject(string $file) : \stdClass
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new InstallerRuntimeError($this, 'Cannot read "' . $file . '"');
        }
        $json = JsonDataType::decodeJson($contents, false);
        if (! $json instanceof \stdClass) {
            throw new InstallerRuntimeError($this, 'Expected a JSON object in "' . $file . '"');
        }
        return $json;
    }

    /**
     * Validates component identities and metadata containers without restricting metadata fields.
     *
     * @param \stdClass $components
     * @param string $file
     * @return void
     */
    private function validateComponents(\stdClass $components, string $file) : void
    {
        foreach ($components as $key => $model) {
            if (! is_string($key) || $key === '' || $key !== mb_strtolower($key) || ! $model instanceof \stdClass) {
                throw new InstallerRuntimeError($this, 'Invalid component "' . $key . '" in "' . $file . '"');
            }
        }
    }

    /**
     * Requires exactly one owning app for every installed component.
     *
     * @param \stdClass $registry
     * @param string $file
     * @return void
     */
    private function validateRegistry(\stdClass $registry, string $file) : void
    {
        $components = $registry->{ComponentRegistry::CONFIG_COMPONENTS} ?? null;
        $sources = $registry->{ComponentRegistry::CONFIG_SOURCES} ?? null;
        if (! $components instanceof \stdClass || ! $sources instanceof \stdClass) {
            throw new InstallerRuntimeError($this, 'Missing COMPONENTS or SOURCES object in "' . $file . '"');
        }
        $this->validateComponents($components, $file);
        $owners = [];
        foreach ($sources as $alias => $keys) {
            if (! is_string($alias) || $alias === '' || ! is_array($keys) || ! array_is_list($keys)) {
                throw new InstallerRuntimeError($this, 'Invalid component sources in "' . $file . '"');
            }
            foreach ($keys as $key) {
                if (! is_string($key) || ! property_exists($components, $key) || isset($owners[$key])) {
                    throw new InstallerRuntimeError($this, 'Invalid component ownership in "' . $file . '"');
                }
                $owners[$key] = $alias;
            }
        }
        if (count($owners) !== count((array) $components)) {
            throw new InstallerRuntimeError($this, 'Component without an owning app in "' . $file . '"');
        }
    }
}
