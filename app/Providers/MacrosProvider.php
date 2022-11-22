<?php

namespace Adshares\Adserver\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class MacrosProvider extends ServiceProvider
{
    public function register(): void
    {
        self::registerBuilderMacros();
    }

    private static function registerBuilderMacros(): void
    {
        Collection::make(glob(__DIR__ . '/BuilderMacros/*.php'))
            ->map(function ($path) {
                return pathinfo($path, PATHINFO_FILENAME);
            })
            ->reject(function ($macro) {
                return Builder::hasGlobalMacro($macro);
            })
            ->each(function ($macro) {
                $macroClass = 'Adshares\\Adserver\\Providers\\BuilderMacros\\' . $macro;
                Builder::macro(Str::camel($macro), app($macroClass)());
            });
    }
}
