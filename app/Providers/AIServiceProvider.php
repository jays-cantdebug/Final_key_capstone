<?php

declare(strict_types=1);

namespace App\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Factories\AIProviderFactory;
use App\AI\Services\AIService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AI Prediction Module's interface, factory, and service into
 * the container. AIProviderInterface always resolves to whichever
 * provider AIProviderFactory selects via config('ai.default'), so
 * switching providers never requires changing consuming code.
 */
class AIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIProviderFactory::class);

        $this->app->bind(AIProviderInterface::class, function ($app): AIProviderInterface {
            return $app->make(AIProviderFactory::class)->make();
        });

        $this->app->singleton(AIService::class);
    }
}
