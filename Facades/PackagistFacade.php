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
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\ComparatorDataType;


class PackagistFacade extends AbstractHttpFacade
{
    private $appVersion = null;
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = new AuthenticationMiddleware($this,[[AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']]);
        $token = $middleware->extractBasicHttpAuthToken($request, $this);
        if (!$token) {
            return new Response(400, [], 'No Basic-Auth data provided!');
        }
        try {
            $this->getWorkbench()->getSecurity()->authenticate($token);
        } catch (AuthenticationFailedError $e) {
            return new Response(400, [], 'Authentification failed!');
        }
        $uri = $request->getUri();
        $path = $uri->getPath();
        $topics = explode('/',substr(StringDataType::substringAfter($path, $this->getUrlRouteDefault()), 1));
        $workbench = $this->getWorkbench();
        if ($topics[0] === 'packages') {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.APP');
            $ds->getColumns()->addMultiple(['FOLDER', 'PACKAGE', 'PACKAGE__version', 'ALIAS']);
            /*$ds->getColumns()->addFromExpression('FOLDER');
            $ds->getColumns()->addFromExpression('PACKAGE');
            $ds->getColumns()->addFromExpression('PACKAGE__version');
            $ds->getColumns()->addFromExpression('ALIAS');*/
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
                $composerJson['version'] = 'dev-master';
                $composerJson['dist'] = [
                    'type' => 'zip',
                    'url' => $this->getPackageUrl($app),
                    'reference' => $this->getAppVersion()
                ];
                $json['packages'][$composerJson['name']] = [
                    'dev-master' => $composerJson
                ]; 
            }
            /*$value = [
                'packages' => [
                    'bachelor/test' => [
                        'dev-master' => [
                            'name' => 'bachelor/test',
                            'version' => 'dev-master',
                            'dist' => [
                                'type' => 'zip',
                                'url' => 'C:/wamp/www/powerui/vendor/bachelor/test2.zip',
                                'reference' => 'test5',
                            ],
                            'time' => '2021-03-30 14:54:14',
                            'require' => [
                                'exface/core' => '^1.0',
                            ],
                            'autoload' => [
                                'psr-4' => [
                                    '\\bachelor\\test\\' => '',
                                ],
                                'exclude-from-classmap' => [
                                    0 => '/Config/',
                                    1 => '/Translations/',
                                    2 => '/Model/',
                                ],
                            ],
                            'extra' => [
                                'app' => [
                                    'app_uid' => '0x11eb8bcaef76908c8bca8c04ba002958',
                                    'app_alias' => 'bachelor.test',
                                    'model_md5' => '942de9702acc046819d46eed0baf37d2',
                                    'model_timestamp' => '2021-03-17 14:54:14',
                                ],
                            ],
                        ],
                    ],
                ],
            ];*/
            $headers = [];
            $headers['Content-type'] = ['application/json;charset=utf-8'];
            $body = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return new Response(200, $headers, $body);
        }
        return new Response(400);
    }
    
    public function getPackageUrl(AppInterface $app) : string
    {
        return $this->getWorkbench()->getUrl() . $this->getUrlRouteDefault() . '/' . mb_strtolower($app->getVendor() . '/' . str_replace($app->getVendor() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '', $app->getAliasWithNamespace()));
    }

    /**
     * 
     * @return string
     */
    protected function getAppVersion(): string
    {
        if (!$this->appVersion) {
            $this->appVersion = date('Ymd_Hm');
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