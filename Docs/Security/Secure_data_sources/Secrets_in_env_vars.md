# Secrets in environment variables

While data connections configs are encrypted by default, they are still explicitly visible to users with respective access rights. You can store secrets in environment variables to hide them from the UI completely. This will also allow to change these secrets without having access to the administration UI (which is useful for CI/CD pipelines).

To reference an environment variable in a data connection config, use the following syntax:

```
{
  "host": "localhost",
  "user": "",
  "password": "[#~env:ENV_VAR_NAME#]"
}
```
Where `ENV_VAR_NAME` is the name of the environment variable that contains the secret value.