<?php

namespace App\Providers;

use App\Modules\Catalog\Domain\Contracts\CatalogProductRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentCatalogProductRepository;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeEventRepository;
use App\Modules\BackofficeOps\Domain\Contracts\BackofficeOrderRepository;
use App\Modules\BackofficeOps\Infrastructure\Persistence\EloquentBackofficeEventRepository;
use App\Modules\BackofficeOps\Infrastructure\Persistence\EloquentBackofficeOrderRepository;
use App\Modules\IdentityAccess\Domain\Contracts\TokenManager;
use App\Modules\IdentityAccess\Domain\Contracts\UserIdentityRepository;
use App\Modules\IdentityAccess\Infrastructure\Auth\JwtTokenManager;
use App\Modules\IdentityAccess\Infrastructure\Persistence\EloquentUserIdentityRepository;
use App\Modules\LiveArtBooking\Domain\Contracts\LiveArtRequestRepository;
use App\Modules\LiveArtBooking\Infrastructure\Persistence\EloquentLiveArtRequestRepository;
use App\Modules\MediaAssets\Domain\Contracts\MediaAssetRepository;
use App\Modules\MediaAssets\Infrastructure\Persistence\EloquentMediaAssetRepository;
use App\Modules\OrderManagement\Domain\Contracts\CartItemRepository;
use App\Modules\OrderManagement\Domain\Contracts\CartRepository;
use App\Modules\OrderManagement\Infrastructure\Persistence\EloquentCartItemRepository;
use App\Modules\OrderManagement\Infrastructure\Persistence\EloquentCartRepository;
use App\Modules\Notifications\Domain\Contracts\OperationalNotificationRepository;
use App\Modules\Notifications\Infrastructure\Persistence\EloquentOperationalNotificationRepository;
use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BackofficeOrderRepository::class, EloquentBackofficeOrderRepository::class);
        $this->app->bind(BackofficeEventRepository::class, EloquentBackofficeEventRepository::class);
        $this->app->bind(CatalogProductRepository::class, EloquentCatalogProductRepository::class);
        $this->app->bind(UserIdentityRepository::class, EloquentUserIdentityRepository::class);
        $this->app->bind(TokenManager::class, JwtTokenManager::class);
        $this->app->bind(LiveArtRequestRepository::class, EloquentLiveArtRequestRepository::class);
        $this->app->bind(MediaAssetRepository::class, EloquentMediaAssetRepository::class);
        $this->app->bind(CartRepository::class, EloquentCartRepository::class);
        $this->app->bind(CartItemRepository::class, EloquentCartItemRepository::class);
        $this->app->bind(OperationalNotificationRepository::class, EloquentOperationalNotificationRepository::class);
    }

    public function boot(): void
    {
    }
}
