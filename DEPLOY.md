# Micro-Coach Quiz Platform Deploy

This guide is for the main assessment plugin in this repository.

It does not apply to the standalone roadmap plugin in `standalone-plugins/product-roadmap-manager/`.

## Scope

WordPress plugin name:

```text
Micro-Coach Quiz Platform
```

Plugin directory on the server:

```text
wp-content/plugins/Employee-assessment-tool-main
```

Main plugin file:

```text
mi-quiz-platform.php
```

## Local Source

Source of truth is the repository root:

```text
/Users/joshchalmers/dev/What You're Good At
```

Do not deploy from:

```text
standalone-plugins/product-roadmap-manager
```

Do not deploy from the local WordPress mirror under:

```text
wp-content/plugins/Employee-assessment-tool-main
```

That local mirror is incomplete and should not be treated as the release artifact.

## Live Target

SSH host alias:

```bash
goodat
```

Live WordPress root:

```text
/home/u321468733/websites/zM7Odl4U5/public_html
```

Live plugin directory:

```text
/home/u321468733/websites/zM7Odl4U5/public_html/wp-content/plugins/Employee-assessment-tool-main
```

## Preflight

This plugin loads Composer dependencies from:

```text
vendor/autoload.php
```

The repo root currently tracks `composer.json` and `composer.lock`, but may not always have a built `vendor/` directory present.

Before any deploy:

```bash
cd "/Users/joshchalmers/dev/What You're Good At"
composer install
test -f vendor/autoload.php
```

Do not run a destructive sync with `--delete` unless `vendor/autoload.php` exists locally.

## Deploy

From the repo root:

```bash
rsync -av --delete -e ssh \
  --exclude '.git/' \
  --exclude 'wp-content/' \
  --exclude 'standalone-plugins/' \
  --exclude '.DS_Store' \
  --exclude '.cursorrules' \
  --exclude '.gitignore' \
  --exclude '*.md' \
  ./ \
  goodat:/home/u321468733/websites/zM7Odl4U5/public_html/wp-content/plugins/Employee-assessment-tool-main/
```

This syncs the main plugin only and avoids pushing the local WordPress mirror, git metadata, and the separate standalone plugin.

## Verify Files

Check that the main plugin file and Composer autoloader are present:

```bash
ssh goodat 'find /home/u321468733/websites/zM7Odl4U5/public_html/wp-content/plugins/Employee-assessment-tool-main -maxdepth 2 -type f | sort | grep -E "mi-quiz-platform.php|vendor/autoload.php|includes/class-mc-benchmarking.php"'
```

## Check Plugin Status

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin list | grep Employee-assessment-tool-main'
```

## Activate Plugin

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin activate Employee-assessment-tool-main'
```

## Deactivate Plugin

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin deactivate Employee-assessment-tool-main'
```

## Cache Notes

The plugin uses `filemtime(...)` for several asset versions. If a deploy is correct but the UI still looks stale, hard refresh the browser once.

## Safety Notes

- Always run `composer install` locally before deploy if `vendor/` is missing.
- Never use the standalone plugin deploy command for this plugin.
- Never sync `wp-content/plugins/Employee-assessment-tool-main` from the local WordPress mirror back to production.
