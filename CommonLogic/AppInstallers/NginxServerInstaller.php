<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;

/**
 * Creates and maintains an nginx.conf file for the current installation.
 * 
 * The nginx.conf file will only take care of the installation's URL path and will not interfere with other locations 
 * or server blocks.
 * 
 * To use this config file on a server, it MUST be included in the global nginx.conf explicitly:
 * 
 * ```
 * server {
 *  listen 8080;
 *  listen [::]:8080;
 *  root /home/site/wwwroot/<workbenchfolder>/current;
 *  index  index.php index.html index.htm;
 *  server_name <my.domain.com>;
 *  # Security settings
 *  server_tokens off;
 * 
 *  # Allow large file uploads
 *  client_max_body_size 1000M;
 *
 *  #include location configurations from workbench folders
 *  include /home/site/wwwroot/workbenchfolder/current/nginx.conf;
 * }
 * 
 * ```
 */
class NginxServerInstaller extends AbstractServerInstaller
{
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        // Placeholders to be used in the nginx.conf files
        $workbenchPath = $this->getWorkbench()->getInstallationPath();
        $workbenchUrl = $this->getWorkbench()->getUrl();
        $workbenchHost = UrlDataType::findHost($workbenchUrl);
        $urlPath = UrlDataType::findPath($workbenchUrl); 
        $urlPath = trim($urlPath, '/');
        $phRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $phRenderer->addPlaceholder(new ArrayPlaceholders([
            'installation_url_path' => $urlPath,
            'installation_absolute_path' => $workbenchPath,
            'host' => $workbenchHost
        ]));
        
        $this->getConfigInstaller()
            ->setMissingMarkerBehavior(FileContentInstaller::MISSING_MARKER_BEHAVIOR_ERROR)
            ->setPlaceholderRenderer($phRenderer)
            ->addContent('Location', $this->buildConfigForLocation($urlPath, $workbenchPath));
    }

    protected function getServerFamily() : string
    {
        return 'nginx';
    }

    protected function buildConfigForLocation(string $urlPath, string $folderPathAbsolute) : string
    {
        $urlPathWithSlash = $urlPath ? $urlPath . '/' : '';
        return <<<CONF

# URL /{$urlPath}
location /{$urlPath} {
    # Redirect everything to the API 
    try_files \$uri \$uri/ /vendor/exface/core/index.php?\$args;
}
    
# Security restrictions
# These have higher priority than try_files as they are more specific!
location /{$urlPathWithSlash}config { return 403; }
location /{$urlPathWithSlash}backup { return 403; }
location /{$urlPathWithSlash}translations { return 403; }
location /{$urlPathWithSlash}logs { return 403; }
location /{$urlPathWithSlash}nginx.conf { return 403; }
location ~ ^/{$urlPathWithSlash}data/\..*$ { return 403; }

location ~* ^/{$urlPathWithSlash}vendor/.*\.html$ { return 404; }
location ~* ^/{$urlPathWithSlash}vendor/.*/gh-pages.*$ { return 404; }
    
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