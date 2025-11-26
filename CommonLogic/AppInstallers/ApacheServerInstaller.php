<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;

class ApacheServerInstaller extends AbstractServerInstaller
{
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        $this->configInstaller
            ->addContent('Core URLs', $this->getContentCoreUrls())
            ->addContent('Core Security', $this->getContentSecurityRules())
            ->addContent("zlib compression OFF for WebConsoleFacade", "
<If \"'%{THE_REQUEST}' =~ m#api/webconsole#\">
    php_flag zlib.output_compression Off
</If>
            
");;
    }

    /**
     * Returns a string containing config options for core URLs. 
     * 
     * Edit the return value if you want to change the core URL rules.
     * 
     * @return string
     */
    protected function getContentCoreUrls() : string
    {
        return "

# API requests
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/.*$ vendor/exface/core/index.php [L,QSA,NC]

# Force trailing slash on requests to the root folder of the workbench
# E.g. me.com/exface -> me.com/exface/
RewriteCond %{REQUEST_URI} ^$
RewriteRule ^$ %{REQUEST_URI} [R=301]

# index request without any path
RewriteRule ^/?$ vendor/exface/core/index.php [L,QSA]

# Requests to UI pages
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^[^/]*$ vendor/exface/core/index.php [L,QSA]

";
    }

    /**
     * Returns a string containing config options for security rules.
     *
     * Edit the return value if you want to change the core security rules.
     *
     * @return string
     */
    protected function getContentSecurityRules() : string
    {
        return "

# Block direct access to PHP scripts
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_FILENAME} !vendor/exface/core/index.php [NC]
RewriteRule ^vendor/.*\.php$ - [F,L,NC]

# Block requests to config, cache, backup, etc.
RewriteRule ^(config|backup|translations|logs)/.*$ - [F,NC]
# Block requests to system files (starting with a dot) in the data folder
RewriteRule ^data/\..*$ - [F,NC]

# Block .html files.
RewriteRule ^vendor/.*\.html$ - [F,L,NC]

# Block library docs.
RewriteRule ^vendor/.*/gh-pages.*$ - [F,L,NC]

";
    }

    /**
     * @inheritDoc
     */
    protected function getConfigFileName(): string
    {
        return '.htaccess';
    }

    /**
     * @inheritDoc
     */
    protected function getConfigTemplatePathRelative(): string
    {
        return 'default.htaccess';
    }

    /**
     * @inheritDoc
     */
    protected function stringToComment(string $markerText): string
    {
        return "# {$markerText}";
    }
}