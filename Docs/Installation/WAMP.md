# Setting up WAMP server on Windows

## Download and install

## Required configuration

### PHP extension `sodium`

TODO 

### PHP extension `sqlsrv` if you plan to use Microsoft SQL Server for model DB

To install the model DB on SQL server your MUST enable the [sqlsrv PHP extension](https://github.com/microsoft/msphpsql/releases) before you start the installer! The extension MUST be enabled for the command line too.

1. Download the extension for your PHP version [here](https://github.com/microsoft/msphpsql/releases).
2. Copy the file `php_sqlsrv_74_ts.dll` (or any other version) to `wamp/bin/php/<version>/ext`
3. Add the following line to `wamp/bin/php/phpForApache.ini` **AND** `wamp/bin/php/phpForApache.ini` (the latter being used for CLI) somewhere among the other extensions: `extension=php_sqlsrv_74_ts.dll`
4. Restart all services in WAMP

## Recommended PHP settings

Use the following configuration in addition to the server-independent [recommendations](Recommended_PHP_settings.md).

## Installing additional PHP extensions

TODO

## Adding SSL certificates

1. Install [OpenSSL for Windows](https://slproweb.com/products/Win32OpenSSL.html)
2. Create a folder to work in (e.g. `c:\certs`)
3. Open a command prompt (`cmd`) as administrator and go to the created folder (`cd c:\certs`).
4. Create a private key via `openssl genrsa -out servername.2022.key 2048` - where 2022 is the current year (this has proven handy when renewing certificates later)
5. Create a certificate request (CSR): create an empty text file named `req.conf` and add the contents below.
6. Ask your IT department create a `.crt` file from the `req.conf`. Rename the resulting `.crt` to something similar to the private key file - in our case that would be `servername.2022.key`
7. Put the files `servername.2022.key` and `servername.2022.crt` in a folder accessible for Apache - e.g. `c:\wamp\bin\Apache\apache2.4.52\conf\certs`. 
8. Change the files `conf\httpd.conf` and `conf\extra\httpd-ssl.conf` inside your current Apache folder as shown in the chapters below


### CSR template

This file must be provided to the authority, that will issue the SSL certificate (probably your IT department). It is important to add all URL you will use to access your server as `DNS.x` entries

```
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no
[req_distinguished_name]
C = {country code - e.g. DE}
ST = {state code - e.g. BY}
L = {your city - e.g. Berlin}
O = {your company}
OU = {your business unit}
CN = {yourserver.yourdomain.com}
[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names
[alt_names]
DNS.1 = yourserver.yourdomain.com
DNS.2 = yourserver
```

### httpd.conf

Open `c:/wamp/bin/apache/apache2.x.xx/conf/httpd.conf` and un-comment (remove the #) the following 3 lines:

```
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
```

### httpd-ssl.conf

Replace the contents of `conf\extra\httpd-ssl.conf` with the following (make sure to change the names of the certificates as required!).

```
#
# This is the Apache server configuration file providing SSL support.
# Required modules: mod_log_config, mod_setenvif, mod_ssl,
#          socache_shmcb_module (for default value of SSLSessionCache)

# When we also provide SSL we have to listen to the
# standard HTTP port (see above) and to the HTTPS port
#
Listen 0.0.0.0:443
Listen [::0]:443

#   SSL Cipher Suite:
SSLCipherSuite HIGH:!RSA:!RC4:!3DES:!DES:!IDEA:!MD5:!aNULL:!eNULL:!EXP

#   User agents such as web browsers are not configured for the user's
#   own preference of either security or performance, therefore this
#   must be the prerogative of the web server administrator who manages
#   cpu load versus confidentiality, so enforce the server's cipher order.
SSLHonorCipherOrder on
SSLCompression      off
SSLSessionTickets   on

#   SSL Protocol support:
#   List the protocol versions which clients are allowed to connect with.
#   Disable SSLv3 by default (cf. RFC 7525 3.1.1).  TLSv1 (1.0) should be
#   disabled as quickly as practical.  By the end of 2016, only the TLSv1.2
#   protocol or later should remain in use.
SSLProtocol all -SSLv2 -TLSv1 -TLSv1.1 -SSLv3

#   Pass Phrase Dialog:
#   Configure the pass phrase gathering process.
#   The filtering dialog program (`builtin' is an internal
#   terminal dialog) has to provide the pass phrase on stdout.
SSLPassPhraseDialog  builtin

#   Inter-Process Session Cache:
#   Configure the SSL Session Cache: First the mechanism
#   to use and second the expiring timeout (in seconds).
SSLSessionCache        "shmcb:${INSTALL_DIR}/logs/ssl_scache(512000)"
SSLSessionCacheTimeout  300

##
## SSL Virtual Host Context
##

<VirtualHost *:443>
	ServerName localhost
  DocumentRoot "c:/wamp/www"
  ServerAdmin {your email address}
	ErrorLog "${INSTALL_DIR}/logs/error.log"
	TransferLog "${INSTALL_DIR}/logs/access.log"
	SSLEngine on
	SSLOptions +FakeBasicAuth +ExportCertData +StrictRequire
	SSLCertificateFile      "${SRVROOT}/conf/key/yourserver.2022.crt"
	SSLCertificateKeyFile   "${SRVROOT}/conf/key/yourserver.2022.key"
#	SSLCACertificateFile    "${SRVROOT}/conf/Certs/Cacerts/Ca.crt"
#
	SSLVerifyClient none
	SSLVerifyDepth  10

	<Directory "c:/wamp/www">
		Options -Indexes +Includes +FollowSymLinks -MultiViews
		AllowOverride all
		Require all granted
	</Directory>
	<FilesMatch "\.(cgi|shtml|phtml|php)$">
		SSLOptions +StdEnvVars
	</FilesMatch>

	BrowserMatch "MSIE [2-5]" nokeepalive ssl-unclean-shutdown downgrade-1.0 force-response-1.0
	CustomLog "${INSTALL_DIR}/logs/custom.log" "%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"
</VirtualHost>
```
	