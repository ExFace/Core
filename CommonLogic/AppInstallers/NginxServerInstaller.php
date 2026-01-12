<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;

class NginxServerInstaller extends AbstractServerInstaller
{
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        // Placeholders to be used in the nginx.conf files
        $workbenchPath = $this->getWorkbench()->getInstallationPath();
        $workbenchUrl = $this->getWorkbench()->getUrl();
        $workbenchHost = UrlDataType::findHost($workbenchPath);
        $urlPath = UrlDataType::findPath($workbenchUrl); 
        $phRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $phRenderer->addPlaceholder(new ArrayPlaceholders([
            'installation_url_path' => $urlPath,
            'installation_absolute_path' => $workbenchPath,
            'host' => $workbenchHost
        ]));
        
        $this->configInstaller
            ->setMissingMarkerBehavior($this->configInstaller::MISSING_MARKER_BEHAVIOR_ERROR)
            ->addContent('Locations', $this->getLocationsContent($urlPath));
    }

    protected function getServerFamily() : string
    {
        return 'nginx';
    }

    protected function getLocationsContent(string $urlPath) : string
    {
        return <<<CONF

    if (!-e \$request_filename){
        rewrite ^/api/.*$ /vendor/exface/core/index.php;
    }

    if (\$request_uri ~ "^$")
        rewrite ^/$ /\$request_uri redirect;
    }
    
    rewrite ^/?$ /vendor/exface/core/index.php;
    
    if (!-e \$request_filename){
        rewrite ^/[^/]*$ /vendor/exface/core/index.php;
    }
    
    # Security restrictions
    location /{$urlPath}/config { return 403; }
    location /{$urlPath}/backup { return 403; }
    location /{$urlPath}/translations { return 403; }
    location /{$urlPath}/logs { return 403; }
    location ~ ^/{$urlPath}/data/\..*$ { return 403; }

    location ~* ^/{$urlPath}/vendor/.*\.html$ { return 404; }
    location ~* ^/{$urlPath}/vendor/.*/gh-pages.*$ { return 404; }

    # Disable .git directory
    location ~ /\.git { deny all; access_log off; log_not_found off; }
}
CONF;
    }

    /**
     * @inheritDoc
     */
    protected function getConfigFileName(): string
    {
        return 'nginx.conf';
    }

    /**
     * @inheritDoc
     */
    protected function getConfigTemplatePathRelative(): string
    {
        return 'default.nginx.conf';
    }

    /**
     * @inheritDoc
     */
    protected function stringToComment(string $comment): string
    {
        return "# {$comment}";
    }
}