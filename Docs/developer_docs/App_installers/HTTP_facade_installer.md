# HTTP facade installer

Configures the routing for an HTTP facade. The facade MUST implement the HttpFacadeInterface!

Technically this installer registeres routes for it's HTTP facade (see HttpFacadeInterface::getUrlRoutePattenrs()) in the system's facade routing configuration (System.config.json > FACADES.ROUTES).

## <a name="init"></a>Initializing the installer

Add something like this to the `getInstaller()` method of your app class:

```
...preceding installers here...
        
$facadeInstaller = new HttpFacadeInstaller($this->getSelector());
$facadeInstaller->setFacade(FacadeFactory::createFromString(YourFacade::class, $this->getWorkbench()));
$installer->addInstaller($facadeInstaller);
 
...subsequent installers here...
```