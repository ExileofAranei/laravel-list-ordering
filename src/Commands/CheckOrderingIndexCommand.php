<?php

namespace ExileOfAranei\ListOrdering\Commands;

use ExileOfAranei\ListOrdering\Contracts\Orderable;
use ExileOfAranei\ListOrdering\Internal\IndexInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CheckOrderingIndexCommand extends Command
{
    public $signature = 'list-ordering:check-index {model?} {--all}';

    public $description = 'Check that a model\'s declared ordering columns match its unique index';

    public function handle(IndexInspector $inspector): int
    {
        $models = $this->option('all')
            ? $this->discoverOrderableModels()
            : array_filter([$this->argument('model')]);

        if ($models === []) {
            $this->components->error('Pass a model class, or --all to scan app/ for Orderable models.');

            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        foreach ($models as $modelClass) {
            $problems = $inspector->diff($modelClass);

            if ($problems === []) {
                $this->components->info("{$modelClass}: OK");

                continue;
            }

            $exitCode = self::FAILURE;
            $this->components->error("{$modelClass}: index mismatch");

            foreach ($problems as $problem) {
                $this->line("  - {$problem}");
            }
        }

        return $exitCode;
    }

    /**
     * Best-effort discovery: scans app/ for classes implementing Orderable.
     * A model outside app/ won't be found this way — pass it by name instead.
     *
     * @return list<string>
     */
    private function discoverOrderableModels(): array
    {
        if (! is_dir(app_path())) {
            return [];
        }

        $namespace = app()->getNamespace();
        $models = [];

        foreach (File::allFiles(app_path()) as $file) {
            $relative = Str::after($file->getPathname(), app_path().DIRECTORY_SEPARATOR);
            $class = $namespace.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (! class_exists($class)) {
                continue;
            }

            if (in_array(Orderable::class, class_implements($class) ?: [], true)) {
                $models[] = $class;
            }
        }

        return $models;
    }
}
