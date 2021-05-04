<?php

namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\DataTypes\StringDataType;
use GuzzleHttp\Psr7\Response;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\Exceptions\Security\AuthenticationFailedError;


class PayloadPackagesFacade extends AbstractHttpFacade
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = new AuthenticationMiddleware($this,[[AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']]);
        $token = $middleware->extractBasicHttpAuthToken($request, $this);
        if (!$token) {
            return new Response(400);
        }
        try {
            $this->getWorkbench()->getSecurity()->authenticate($token);
        } catch (AuthenticationFailedError $e) {
            return new Response(400);
        }
        $uri = $request->getUri();
        $path = $uri->getPath();
        $topics = explode('/',substr(StringDataType::substringAfter($path, $this->getUrlRouteDefault()), 1));
        if ($topics[0] === 'packages') {
            $value = [
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
            ];
            $headers = [];
            $headers['Content-type'] = ['application/json;charset=utf-8'];
            $body = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return new Response(200, $headers, $body);
        }
        return new Response(400);
    }

    public function getUrlRouteDefault(): string
    {
        return 'api/payloadpackages';
    }

    
}