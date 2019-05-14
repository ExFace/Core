# Run CLI commands from the `Console` widget

With the `Console` widget you can integrate command line (CLI) actions into the UI. It can be either used as an interactive web console or to display the output of a predefined console command in a pretty way.

In a nutshell, the `Console` widget can:

- Let the user execute allowed commands,
- Execute one or more commands automatically,
- Provide predefined commands, that users can pick from a menu and execute by click,

**WARNING**: allowing users to execute console commands on the server may be a serious security issue! Take great care to restrict the access to interactive (i.e. not disabled) `Console` widgets to high-level admins only. By default, the `Console` will not allow any commands to be executed: you need to specify, which commands the user will be able to execute. Use regular expressions to allow multiple commands with a single config line.

## Opening an interactive console

Here is how to create a button, that openes a dialog with an interactive console, that can perform `cd`, `dir` as well as all `git` commands. The button will create a dailog with a single widget inside - the `Console`.

```
{
	"widget_type": "Button",
	"action": {
		"alias": "exface.Core.ShowDialog",
		"widget": {
			"widget_type": "Dialog",
			"widgets": [
				{
					"widget_type": "Console",
					"allowed_commands": [
						"/cd.*/",
						"/dir.*/",
						"/git .*/"
					]
				}
			]
		}
	}
}

```

**Note:** By default, the Console will start in the current installation folder. You can change this by adding a `cd` command to `commands_on_start`.

## Providing a button to perform a CLI command

In many cases, only a small number of commands is required: e.g. to allow users to preform a composer update, you won't need an interactive console - a single button executing `php composer update` will be enough. However, the result of the command should still be displayed in console style.

Use disabled `Console` widgets with `commands_on_start` to achieve this behavior. Here is the code for the composer update:

```
{
	"widget_type": "Button",
	"caption": "Composer Update",
	"action": {
		"alias": "exface.Core.ShowDialog",
		"widget": {
			"widgets": [
				{
					"widget_type": "Console",
					"disabled": true,
					"start_commands": [
						"php composer.phar update"
					]
				}
			]
		}
	}
}

```

This button will open a console dialog and run composer update automatically. The result will be displayed in the console, but the user will not be able to add other commands.

The command will be run in the installaton folder. If your command requires another folder, add a `cd` command before.

## Using data placeholders

If the commands are somehow related to data, the user previously worked on, you can use `[#column#]` placeholders similarly to actions like `GoToUrl`. The following button will perform a composer update on a specific app and show the result in a console dialog:

```
{
	"caption": "Composer Update",
	"action": {
		"alias": "exface.Core.ShowDialog",
		"object_alias": "exface.Core.APP",
		"input_rows_min": 1,
		"input_rows_max": 1,
		"widget": {
			"widgets": [
				{
					"widget_type": "Console",
					"disabled": true,
					"start_commands": [
						"php composer.phar update [#PACKAGE#]"
					]
				}
			]
		}
	}
}

```

The placeholder `[#PACKAGE#]` will be automatically replaced by the the value of the `PACKAGE` attribute of the selected app.

## Adding command presets to an interactive console

The following example creates a simple git console, that let's the user commit and push all changes, list branches and switch between them. Depending on the facade implementation, the user will be able to pick a preset from a menu or type any of these commands manually.

```
{
	"widget_type": "Console",
	"command_prsets": [
		{
			"caption": "Commit/Push all",
			"hint": "Commits all local changes and pushes them to the current remote",
			"commands": [
				"git commit -a -m <message>",
				"git push"
			]
		},
		{
			"caption": "List branches",
			"commands": [
				"git branch -a"
			]
		},
		{
			"caption": "Switch branch",
			"commands": [
				"git checkout <branch>"
			]
		}
	]
}

```

A preset may contain a single command or an array with commands. If at least one of the commands contains a placeholder (e.g. `<message>`), the user will be asked to fill-in values for all placeholders in the preset befor the commands are executed: most facades will show a small input-dialog when the user presses the preset-button.

**Note:** command presets are automatically added to the allowed commands. In the above example, users will be able to execute only the 4 commands, that are part of the presets. Command placeholders like `<message>` will be treated as wildcards.