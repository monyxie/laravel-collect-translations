<?php

namespace Monyxie\CollectTranslation;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Monyxie\CollectTranslation\Commands\TranslationCollect;

class ServiceProvider extends LaravelServiceProvider {
    /**
     * @return void
     */
    public function boot() {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TranslationCollect::class,
            ]);
        }
    }
}