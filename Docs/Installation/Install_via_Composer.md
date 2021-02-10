# Install via composer

1. Create a folder for installation whereever it is accessible via web server (e.g. `c:\wamp\www\exface` if you are running the WAMP server on Windows)
2. Create a text file called `composer.json` inside that folder with the contents from on of the subsections below. 
3. Create a subfolder named `Config` with the initial config files for your [metamodel database](Configure_metamodel_DB.md) and your [server environment](Initial_configuration.md).
4. Make sure Composer is installed or simply download `composer.phar` from [here](https://getcomposer.org/download/) and copy it to the installation folder.
5. Execute `php composer install` on the command line (or `php composer.phar install` if you downloaded the phar manually).
	
## Typical minimum setup (stable versions)

This will install the latest stable version and a typical minimum set of extras with open source licenses. 

```
{
  "require": {
    "exface/Core": "^1.0",
    "axenox/PackageManager": "^1.0",
    "exface/UrlDataConnector": "^1.0",
    "exface/JEasyUIFacade": "^1.0",
    "bower-asset/jeasyui": "1.4.3"
  },
  "repositories": [
    {
      "type": "composer",
      "url": "https://asset-packagist.org"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/Core"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/UrlDataConnector"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/JEasyUiFacade"
    },
    {
      "type": "vcs",
      "url": "https://github.com/axenox/PackageManager"
    }
  ],
  "optimize-autoloader": true,
  "autoload": {
    "psr-0": {
      "axenox\\PackageManager": "vendor/"
    }
  },
	"scripts": {
	    "post-update-cmd": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishUpdate"
	    ],
	    "post-install-cmd": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishInstall"
	    ],
	    "post-package-install": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishPackageInstall"
	    ],
	    "post-package-update": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishPackageUpdate"
	    ]
	}
}
```

## Typical minimum setup with nightly builds

```
{
  "require": {
    "exface/Core": "dev-1.x-dev as 1.99",
    "axenox/PackageManager": "dev-1.x-dev as 1.99",
    "exface/UrlDataConnector": "dev-1.x-dev as 1.99",
    "exface/JEasyUIFacade": "dev-1.x-dev as 1.99",
    "bower-asset/jeasyui": "1.4.3"
  },
  "repositories": [
    {
      "type": "composer",
      "url": "https://asset-packagist.org"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/Core"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/UrlDataConnector"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/JEasyUiFacade"
    },
    {
      "type": "vcs",
      "url": "https://github.com/axenox/PackageManager"
    }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true,
  "config": {
    "secure-http": false
  },
  "optimize-autoloader": true,
  "autoload": {
    "psr-0": {
      "axenox\\PackageManager": "vendor/"
    }
  },
	"scripts": {
	    "post-update-cmd": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishUpdate"
	    ],
	    "post-install-cmd": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishInstall"
	    ],
	    "post-package-install": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishPackageInstall"
	    ],
	    "post-package-update": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishPackageUpdate"
	    ]
	}
}
```

## SAP UI5 startetr kit (nightlies)

```
{
  "require": {
    "exface/Core": "dev-1.x-dev as 1.99",
    "axenox/PackageManager": "dev-1.x-dev as 1.99",
    "exface/UrlDataConnector": "dev-1.x-dev as 1.99",
    "exface/SapConnector": "dev-1.x-dev as 1.99",
    "exface/UI5Facade": "dev-1.x-dev as 1.99",
    "exface/JEasyUIFacade": "dev-1.x-dev as 1.99",
    "bower-asset/jeasyui": "1.4.3"
  },
  "repositories": [
    {
      "type": "composer",
      "url": "https://asset-packagist.org"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/Core"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/UrlDataConnector"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/SapConnector"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/UI5Facade"
    },
    {
      "type": "vcs",
      "url": "https://github.com/ExFace/JEasyUiFacade"
    },
    {
      "type": "vcs",
      "url": "https://github.com/axenox/PackageManager"
    }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true,
  "config": {
    "secure-http": false
  },
  "optimize-autoloader": true,
  "autoload": {
    "psr-0": {
      "axenox\\PackageManager": "vendor/"
    }
  },
	"scripts": {
	    "post-update-cmd": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishUpdate"
	    ],
	    "post-install-cmd": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishInstall"
	    ],
	    "post-package-install": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishPackageInstall"
	    ],
	    "post-package-update": [
	        "axenox\\PackageManager\\StaticInstaller::composerFinishPackageUpdate"
	    ]
	}
}
```