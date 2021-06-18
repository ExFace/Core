<?php

namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\DataTypes\StringDataType;
use GuzzleHttp\Psr7\Response;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\AppFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ActionFactory;
use axenox\PackageManager\StaticInstaller;
use exface\Core\CommonLogic\ArchiveManager;
use Psr\Http\Message\StreamInterface;


class PackagistFacade extends AbstractHttpFacade
{
    private $appVersion = null;
    
    const BRANCH_NAME = 'dev-puplished';
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = new AuthenticationMiddleware($this,[[AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']]);
        $token = $middleware->extractBasicHttpAuthToken($request, $this);
        if (!$token) {
            return new Response(401, [], 'No Basic-Auth data provided!');
        }
        try {
            $this->getWorkbench()->getSecurity()->authenticate($token);
        } catch (AuthenticationFailedError $e) {
            return new Response(403, [], 'Authentification failed!');
        }
        $uri = $request->getUri();
        $path = $uri->getPath();
        $topics = explode('/',substr(StringDataType::substringAfter($path, $this->getUrlRouteDefault()), 1));
        if ($topics[0] === 'packages') {
            return $this->buildResponsePackagesJson();
        } elseif ($topics[0]) {
            return $this->buildResponsePackage($topics);
        }
        return new Response(404);
    }
    
    /**
     * Returns the response including the packages.json
     * 
     * @return ResponseInterface
     */
    protected function buildResponsePackagesJson() : ResponseInterface
    {
        $workbench = $this->getWorkbench();
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.APP');
        $ds->getColumns()->addMultiple(['FOLDER', 'PACKAGE', 'PACKAGE__version', 'ALIAS', 'PUPLISHED']);
        $ds->getFilters()->addConditionFromString('PUPLISHED', true, ComparatorDataType::EQUALS);
        $ds->dataRead();
        $json = [
            'packages' => []
        ];
        foreach($ds->getRows() as $row) {
            if ($row['PACKAGE__version']) {
                continue;
            }
            $alias = $row['ALIAS'];
            $app = AppFactory::createFromAlias($alias, $workbench);
            $packageManager = $this->getWorkbench()->getApp("axenox.PackageManager");
            $composerJson = $packageManager->getComposerJson($app);
            $composerJson['version'] = self::BRANCH_NAME;
            $composerJson['dist'] = [
                'type' => 'zip',
                'url' => $this->buildPackageUrl($app),
                'reference' => $this->getAppVersion()
            ];
            $json['packages'][$composerJson['name']] = [];            
            $json['packages'][$composerJson['name']][self::BRANCH_NAME] = $composerJson;
            
        }        
        $headers = [];
        $headers['Content-type'] = 'application/json;charset=utf-8';
        $body = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return new Response(200, $headers, $body);
    }
    
    protected function buildResponsePackage(array $topics) : ResponseInterface
    {
        $workbench = $this->getWorkbench();
        $filemanager = $workbench->filemanager();
        $packageAlias = '';
        foreach ($topics as $topic) {
            $packageAlias .= $topic . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
        }
        $packageAlias = substr($packageAlias, 0, -1);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.APP');
        $ds->getColumns()->addMultiple(['ALIAS', 'PUPLISHED']);
        $ds->getFilters()->addConditionFromString('PUPLISHED', true, ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('ALIAS', $packageAlias, ComparatorDataType::EQUALS);
        $ds->dataRead();
        if ($ds->isEmpty()) {
            $packageAlias = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '/', $packageAlias);
            return new Response(404, [], "The package '{$packageAlias}' does not exist or is not puplished!");
        }
        $app = AppFactory::createFromAlias($packageAlias, $workbench);        
        $backupAction = ActionFactory::createFromString($workbench, StaticInstaller::PACKAGE_MANAGER_BACKUP_ACTION_ALIAS);
        $path = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, DIRECTORY_SEPARATOR, $packageAlias) . DIRECTORY_SEPARATOR . $this->getAppVersion();
        $path = $filemanager->getPathToPuplishedFolder() . DIRECTORY_SEPARATOR . $path;
        $backupAction->setBackupPath($path);
        $generator = $backupAction->backup($app->getSelector());
        foreach($generator as $gen) {
            continue;
        }
        $zip = new ArchiveManager($workbench, $path . '.zip');
        $zip->addFolder($path);
        $zip->close();
        $headers = [
            "Content-type" => "application/zip",
            "Content-Transfer-Encoding"=> "Binary",
        ];
        $filemanager->deleteDir($path);
        return new Response(200, $headers, readfile($path . '.zip'));
        
    }
    
    /**
     * Build the URL to include in packages.json for the composer to download the app
     * 
     * @param AppInterface $app
     * @return string
     */
    public function buildPackageUrl(AppInterface $app) : string
    {
        return $this->buildUrlToFacade() . '/' . mb_strtolower($app->getVendor() . '/' . str_replace($app->getVendor() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '', $app->getAliasWithNamespace()));
    }

    /**
     * 
     * @return string
     */
    protected function getAppVersion(): string
    {
        if (!$this->appVersion) {
            $this->appVersion = date('Ymd_Hi');
        }
        return $this->appVersion;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/packagist';
    }

    
}