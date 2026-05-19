# Product Roadmap Manager Deploy

This plugin is a standalone WordPress admin plugin. It is not part of the main assessment plugin and should be deployed as its own plugin directory.

## Local Source

Plugin source lives here:

```text
standalone-plugins/product-roadmap-manager
```

Main plugin file:

```text
standalone-plugins/product-roadmap-manager/product-roadmap-manager.php
```

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
/home/u321468733/websites/zM7Odl4U5/public_html/wp-content/plugins/product-roadmap-manager
```

## Deploy

From the repo root, sync the standalone plugin to production:

```bash
rsync -av --delete -e ssh ./standalone-plugins/product-roadmap-manager/ \
  goodat:/home/u321468733/websites/zM7Odl4U5/public_html/wp-content/plugins/product-roadmap-manager/
```

## Verify Files

```bash
ssh goodat 'find /home/u321468733/websites/zM7Odl4U5/public_html/wp-content/plugins/product-roadmap-manager -maxdepth 3 -type f | sort'
```

## Check Plugin Status

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin list | grep product-roadmap-manager'
```

## Activate Plugin

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin activate product-roadmap-manager'
```

## Deactivate Plugin

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin deactivate product-roadmap-manager'
```

## Remove Plugin

Delete the plugin from WordPress admin or via WP-CLI:

```bash
ssh goodat 'cd /home/u321468733/websites/zM7Odl4U5/public_html && wp plugin delete product-roadmap-manager'
```

`uninstall.php` deletes the plugin's stored option:

```text
prm_task_data
```

## Admin Navigation

After activation:

- `Product Roadmap -> Dashboard` is the task management UI
- `Product Roadmap -> Settings` contains the reset-data action and cleanup instructions

## Cache Notes

This plugin uses file modification times for CSS and JS versioning. If the admin UI still shows stale controls after deploy, hard refresh the browser once.

## Scope Reminder

Do not deploy this into:

```text
wp-content/plugins/Employee-assessment-tool-main
```

It is intentionally isolated from the main assessment plugin.
