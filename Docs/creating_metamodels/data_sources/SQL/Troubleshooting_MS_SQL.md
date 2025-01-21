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