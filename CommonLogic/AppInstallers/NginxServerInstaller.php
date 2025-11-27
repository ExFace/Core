<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;

class NginxServerInstaller extends AbstractServerInstaller
{
    public function __construct(SelectorInterface $selectorToInstall)
    {
        parent::__construct($selectorToInstall);
        
        $this->configInstaller
            ->setMissingMarkerBehavior($this->configInstaller::MISSING_MARKER_BEHAVIOR_ERROR)
            ->addContent('Locations', $this->getLocationsContent());
    }

    protected function getLocationsContent() : string
    {
        return 'location / {
    if (!-e $request_filename){
        rewrite ^/api/.*$ /vendor/exface/Core/index.php;
    }

    if ($request_uri ~ "^$"){
        rewrite ^/$ /$request_uri redirect;
    }
    
    rewrite ^/?$ /vendor/exface/Core/index.php;
    
    if (!-e $request_filename){
        rewrite ^/[^/]*$ /vendor/exface/Core/index.php;
    }
}

location /config {
    return 403;
}

location /backup {
    return 403;
}

location /translations {
    return 403;
}

location /logs {
    return 403;
}

location ~ ^/data/\..*$ {
    return 403;
}

location ~* ^vendor/.*\.html$ {                
        return 403;
}

location ~* ^vendor/.*/gh-pages.*$ {
        return 403;
}

# Disable .git directory
location ~ /\.git {
    deny all;
    access_log off;
    log_not_found off;
}

# Add locations of phpmyadmin here.
location ~ [^/]\.php(/|$) {
    fastcgi_split_path_info ^(.+?\.php)(|/.*)$;
    fastcgi_pass 127.0.0.1:9000;
    include fastcgi_params;
    fastcgi_param HTTP_PROXY "";
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_param QUERY_STRING $query_string;
    fastcgi_intercept_errors on;
    fastcgi_connect_timeout         300;
    fastcgi_send_timeout           3600;
    fastcgi_read_timeout           3600;
    fastcgi_buffer_size 128k;
    fastcgi_buffers 4 256k;
    fastcgi_busy_buffers_size 256k;
    fastcgi_temp_file_write_size 256k;
}';
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