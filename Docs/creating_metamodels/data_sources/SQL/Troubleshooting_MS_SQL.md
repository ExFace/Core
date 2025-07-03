# Troubleshooting Microsoft SQL Server data sources

## Connector errors

### MS SQL Server drivers for PHP (extension "sqlsrv") not installed!

This means, the PHP extension required to connecto to SQL Server is missing. Please install it from here: https://github.com/microsoft/msphpsql.

### cURL error 60: SSL certificate problem: unable to get local issuer certificate

This often happens when trying to connect to Azure from a windows server. On many Windows servers, the default SSL root certificates do not work with Azure. The solution is to download the current ´cacert.pem´ file from a trustworthy source like this one: https://curl.se/docs/caextract.html. Save this file to some location easily accessible from the PHP installation folder and/uncomment add the following line to php.ini:

```
curl.cainfo = C:\your\path\cacert.pem
```

### Failed to create the database connection! Named Pipes Provider: Could not open a connection to SQL Server (Code 2)

This means, the SQL Server cannot be contacted. Try to disable SSL verification:

```
{
   "host": "..."
   "connection_options": {
       "TrustServerCertificate": true
   }
}
```

## DB connection cannot be initialized without an error

Sometimes we saw the connection initialization hanging indefinitely without any error. The reason is unknown, but it 
happened only to the second connection beint initialized to the same DB - in particular, when multiple app installers
were run one-after another.

Specifically for the installers, you can try to force all installers to use the same workbench and, thus, the same
DB connection by setting `COMPOSER.USE_NEW_WORKBENCH_FOR_EVERY_APP` to `false` in `axenox.PackageManager.config.
json` in your config folder.

On Linux systems, you can also try to set `ConnectionPooling` to `true` in the `connection_options` of the MS SQL
connection config in the model.