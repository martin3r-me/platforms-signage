<?php

namespace Platform\Signage;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Digital Signage Service Provider.
 *
 * Folgt dem Muster von module-template / helpdesk:
 * - Config in register()
 * - Modul-Registrierung, Routen, Migrationen, Views, Livewire in boot()
 */
class SignageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/signage.php', 'signage');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Signage\Console\Commands\ProcessDocuments::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Livewire-Standard für temporäre Uploads (12 MB) anheben, damit auch
        // Videos hochgeladen werden können – nur, wenn die App nichts Eigenes gesetzt hat.
        if (config('livewire.temporary_file_upload.rules') === null) {
            $maxKb = (int) config('signage.max_upload_kb', 512000);
            config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.$maxKb]]);
        }

        if (
            config()->has('signage.routing') &&
            config()->has('signage.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'signage',
                'title'      => 'Digital Signage',
                'group'      => 'tools',
                'routing'    => config('signage.routing'),
                'guard'      => config('signage.guard'),
                'navigation' => config('signage.navigation'),
                'sidebar'    => config('signage.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('signage')) {
            // Öffentliche Routen (Player + signierte Medien-Auslieferung)
            ModuleRouter::group('signage', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            // Authentifizierte Admin-Routen
            ModuleRouter::group('signage', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            // Öffentliche Geräte-API (Pairing, State, Manifest) – über device_token autorisiert
            ModuleRouter::apiGroup('signage', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            }, requireAuth: false);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'signage');

        // Eigene UI-Bausteine des Moduls (bewusst losgelöst von x-ui-panel/-tile).
        Blade::component('signage::components.panel', 'signage-panel');
        Blade::component('signage::components.tile', 'signage-tile');

        $this->registerLivewireComponents();
        $this->registerPolicies();

        $this->publishes([
            __DIR__.'/../config/signage.php' => config_path('signage.php'),
        ], 'config');
    }

    /**
     * Registriert alle Policies, sofern Model + Policy existieren.
     */
    protected function registerPolicies(): void
    {
        $map = [
            \Platform\Signage\Models\SignageScreen::class       => \Platform\Signage\Policies\SignageScreenPolicy::class,
            \Platform\Signage\Models\SignageMedia::class        => \Platform\Signage\Policies\SignageMediaPolicy::class,
            \Platform\Signage\Models\SignagePlaylist::class     => \Platform\Signage\Policies\SignagePlaylistPolicy::class,
            \Platform\Signage\Models\SignageSchedule::class     => \Platform\Signage\Policies\SignageSchedulePolicy::class,
            \Platform\Signage\Models\SignageMediaFolder::class  => \Platform\Signage\Policies\SignageMediaFolderPolicy::class,
        ];

        foreach ($map as $model => $policy) {
            if (class_exists($model) && class_exists($policy)) {
                Gate::policy($model, $policy);
            }
        }
    }

    /**
     * Auto-Registrierung aller Livewire-Komponenten aus src/Livewire/.
     *
     * src/Livewire/Dashboard.php       -> signage.dashboard
     * src/Livewire/Screens/Index.php   -> signage.screens.index
     */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__.'/Livewire';
        $baseNamespace = 'Platform\\Signage\\Livewire';
        $prefix = 'signage';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace.'\\'.$classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix.'.'.$aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
