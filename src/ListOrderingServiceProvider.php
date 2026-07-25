<?php

namespace ExileOfAranei\ListOrdering;

use ExileOfAranei\ListOrdering\Commands\CheckOrderingIndexCommand;
use ExileOfAranei\ListOrdering\Contracts\RankGenerator;
use ExileOfAranei\ListOrdering\Generators\FractionalRankGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ListOrderingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-list-ordering')
            ->hasCommand(CheckOrderingIndexCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(RankGenerator::class, FractionalRankGenerator::class);
    }

    public function bootingPackage(): void
    {
        Blueprint::macro('orderingRank', function (string $column = 'rank') {
            /** @var Blueprint $this */
            $definition = $this->string($column, 64);

            return match (DB::getDriverName()) {
                'mysql', 'mariadb' => $definition->charset('binary'),
                'pgsql' => $definition->collation('C'),
                default => $definition,
            };
        });
    }
}
