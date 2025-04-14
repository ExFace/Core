# Troubleshooting Git

## Finding the problem

### View git configs

To check the current git configuraion open the git console an run to command below. This will show not only all configurations, but also the files where they are located, which is very usefull.

`git config --list --show-origin --show-scope`

### Debugging git

To enable debug output for git commands, set the following environment variables before running the command: `GIT_CURL_VERBOSE=1 GIT_TRACE=1`

- On Windows: `SET GIT_CURL_VERBOSE=1`, `SET GIT_TRACE=1`

## Configuration issues

### Missing upstream configuration

- If you cannot push because there is no upstream set up, use `git push --set-upstream origin main` where `origin` is the remote and `main` is the branch.

## Connection/SSL issues

### SSL Certificate problem: unable to get local issuer certificate.

Run `git config --global http.sslbackend schannel` on the command line to solve the issue.

The problem is that git by default using the "Linux" crypto backend.

Beginning with Git for Windows 2.14, you can now configure Git to use SChannel, the built-in Windows networking layer as the crypto backend. This means that it will use the Windows certificate storage mechanism and you do not need to explicitly configure the curl CA storage mechanism: https://msdn.microsoft.com/en-us/library/windows/desktop/aa380123(v=vs.85).aspx

### Timeout on push, but pull working fine

This may happen when using repositories initially created by composer. Try opening the file `.git/config` inside the repo folder and remove the line starting with `pushurl = ` if present.

## Multiple Github accounts

If you have multiple Github accounts in your Git credential manager, web console commands will stop working. Multiple user issues are [explained here in the git docs](https://github.com/git-ecosystem/git-credential-manager/blob/main/docs/multiple-users.md). You can either remove accounts and leave only a single one, or select a default one. Here is how to get rid of unneeded accounts:

```
>git credential-manager github list
4246282
kabachello

>git credential-manager github logout 4246282
```

## Dubious Ownership

If you have a local development installation of PowerUI running with wamp, you might encounter dubious ownership git issues such as this:

```
fatal: detected dubious ownership in repository at '<repository-path>' 

'<repository-path>' is owned by DIR/<user> but the current user is: NT AUTHORITY/SYSTEM 
```

This can happen because the repository files are likely owned by your current windows user, while the wamp process runs as administrator (which is a different user). A workaround for this is to add an exception for the repository to the git config. For this, you need to run CMD as administrator and add the following (systemwide) exception for your repository:

```
git config --system --add safe.directory "<repository-path>"
```
