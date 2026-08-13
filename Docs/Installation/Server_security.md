# Server security

**DISCLAIMER:** The best practices below are just recommendations. Make sure 
to consult with IT security guidelines of your organization! Concider conducting
security assessment and penetration testing to identify potential vulnerabilities 
in your server setup.

## Useful links

- Global guidelines:
  - [OWASP](https://owasp.org/)
  - [OWASP Top 10](https://owasp.org/www-project-top-ten/)
  - [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/)
- [Workbench security documentation](../Security/index.md)

## Checklist

- Server
  - [ ] Use HTTPS only! Block access via HTTP and redirect all HTTP traffic to HTTPS.
  - [ ] use [recommended PHP settings](Recommended_PHP_settings.md) for 
    production environments
  - [ ] Prevent [access to config and data folders](../Security/Securing_installation_folders.md)
  - [ ] Prevent direct access to any PHP files except for `api.php` by means 
    of the web server configuration (e.g. `.htaccess` or `nginx.conf`).
  - [ ] Replace built-in server error pages with empty or custom pages
  - [ ] Configure your server to NOT send its name and version information in HTTP headers
- Workbench
  - [ ] Disable or delete the built-in `admin` user account.
  - [ ] Use strong authentication: e.g. two factors ([password + TOTP](../Security/Authentication/Two-factor_authentication.md))
    or single-sign-on with your organization's identity provider.
  - [ ] Limit the use of `SUPERUSER` accounts on production to a minimum!
- Data sources (including workbench database)
  - [ ] Use managed identities if possible (e.g. in cloud environments). 
    Otherwise use multi-factor authentication (e.g. password + IP-filter)
  - [ ] Use SSL/TLS for database connections if supported
  - [ ] Use data-at-rest encryption in databases if supported