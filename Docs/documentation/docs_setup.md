# Adding documentation to an app

To add documentation to your app, just create a folder called `Docs` in the root of the app and place the file `index.md` within it. That's it: Now your documentation should be visible (and searchable) in Administration > Documentation. 

The built-in documentation module expects your docs to use the Markdown syntax with GitHub extensions. In other words, write your documentation just the way you would do for a GitHub project. Here is a good tutorial from GitHub: https://guides.github.com/features/mastering-markdown/.

You are free to organize your documentation any way you like. Don't have a good structure in your mind? Have a look at our [tips for structuring the docs-folder](docs_structure.md).

If your app is a Composer package and has a sources repository on GitHub, GitLab or similar (which is highly recommended - see [publishing apps](../publishing_apps/index.md) for details), you should add a link to your documentation to the `support` section of the `composer.json` of your app. Here is an example for GitHub/GitLab:

```
{
	"name" : "exface/Core",
	"description" : "Business web application plattform",
	"support" : {
		"source" : "https://github.com/exface/core",
		"docs" : "https://github.com/exface/core/blob/1.x-dev/Docs",
	}
}
```

Doing so will not only tell potential users where to read about our app before installing it, but it will also allow other app's documentation to use links to your app's docs, that will work both, if the app is installed and if it is not - see [crosslinking docs](docs_crosslinking.md) for details.