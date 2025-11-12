# Troubleshooting web server issues

If you see plain text errors displaying HTTP codes like 404 (not found) or 500 (general error), the problem is often not the workbench, but the configuration of the web server it runs on.

## URI too long errors

Most servers will limit the length of URLs by default. Here are some common recipies:

In Apache add the following to the httpd.conf. See [WAMP installation](WAMP.md) for more details:
```
LimitRequestLine 10000
```