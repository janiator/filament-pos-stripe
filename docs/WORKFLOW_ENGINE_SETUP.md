# Filament Workflow Engine Setup

The [Leek Filament Workflows](https://filamentphp.com/plugins/leek-workflow-engine) plugin is wired into the app panel and scheduler.

## Installed and configured

- **Package**: `leek/filament-workflows` installed; `php artisan filament-workflows:install` has been run.
- **Panel**: Plugin is registered in `AppPanelProvider` (navigation group: Automation / Automasjon).
- **Tenancy**: Enabled in `config/filament-workflows.php` with `store_id` and `App\Models\Store`; migration has added `store_id` to workflow tables. Workflows are scoped per store via `Filament::getTenant()`.
- **Queue**: `workflows` queue is in Horizon config.
- **Scheduler**: `workflows:process-scheduled` runs every minute in `routes/console.php`.

## Optional: license for new environments

For a fresh clone or CI, add the workflow engine license to `auth.json` under `filament-workflow-engine.composer.sh` (username = license email, password = license key), then run `composer install`.

## Config and triggerable models

- **Config**: `config/filament-workflows.php` — tenancy, queue, triggerable models, etc.
- **Triggerable models**: Add model classes to `triggerable_models`, or use the `HasWorkflowTriggers` trait and enable `discovery.enabled`.

## Custom theme (Filament v4)

If you use a [custom Filament theme](https://filamentphp.com/docs/4.x/styling/overview#creating-a-custom-theme), add the workflow plugin source in your theme’s `theme.css`:

```css
@source '../../../../vendor/leek/filament-workflows';
```

Then run `npm run build` and `php artisan filament:upgrade`.

See the [plugin docs](https://filamentphp.com/plugins/leek-workflow-engine) for triggers, actions, variable interpolation, and run history.
