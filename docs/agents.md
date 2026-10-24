# AI assistance

The canonical source is `skills/yii2-nested-sets/SKILL.md` in this package. It covers model configuration, queries,
structural writes, migrations and writer-lock constraints. Guides remain in `docs/`; API contracts remain in `src/`
PHPDoc. Edit these sources instead of maintaining separate client copies.

## Install in an application

### Automatic Composer installation

Complete the
[one-time application setup](https://yii2-framework.github.io/docs/project/ai-agents/#composer-installation) to enable
`llm/skills` and set `auto-sync` to `true` in `skills.json`. Approve `yii2-extensions/nested-sets-behavior` explicitly
in `dependencies.composer.trusted`. The installed package must include its skill bundle; its `extra.skills.source`
declares the directory.

```bash
composer install
composer skills:show
```

With `auto-sync` enabled, later dependency installs and updates synchronize the skill automatically; no per-skill
installer is required. If `auto-sync` is `false`, run `composer skills:update` after dependency installation or updates.

For a release without `skills/yii2-nested-sets/SKILL.md`, register a local checkout containing the bundle once:

```bash
composer skills:add /absolute/path/to/nested-sets-behavior --skill=yii2-nested-sets
```

### Optional npx installation

From the application root, use a local checkout containing the skill and select the clients you use:

```bash
npx skills@1.7.2 add /path/to/nested-sets-behavior --list
npx skills@1.7.2 add /path/to/nested-sets-behavior --skill yii2-nested-sets
```

For a public source, list first and install only if the skill is present:

```bash
npx skills@1.7.2 add yii2-extensions/nested-sets-behavior --list
```

Add `--list` to inspect available skills without installing them. Omit `--global` for a project installation. A remote
command installs the repository's selected skill, so use the local path for unpublished changes. Review the chosen
destinations; Composer installing a package under `vendor/` does not register its skills in your application.

## Discovery and use

Client documentation: [Codex skills](https://learn.chatgpt.com/docs/build-skills),
[Claude Code skills](https://code.claude.com/docs/en/skills), and [OpenCode skills](https://opencode.ai/docs/skills/).

Select Claude Code, Codex, or OpenCode in the installer. Use each client's skill list or native invocation mechanism to
check discovery and activate `yii2-nested-sets` for a small task. Installation places files or links; discovery exposes
its name and description; activation loads its instructions. Record those stages separately from successful PHP,
database or concurrency tests. For automatic selection, use a natural tree-operation request without naming the skill.

The skill resolves the installed package and Yii versions before selecting documentation. A copied skill does not
require neighboring `docs/` files; it can use matching repository documentation or local source/PHPDoc and changelog
when hosted pages are unavailable. See [AI assistance](../README.md#ai-assistance) for the framework documentation index
and framework skill. Never substitute current hosted APIs for an older installation without verifying them.

[Back to documentation](../README.md#documentation)
