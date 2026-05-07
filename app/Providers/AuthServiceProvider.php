<?php

namespace App\Providers;

use App\Models\BlogArticle;
use App\Models\BoosterApplication;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\User;
use App\Policies\BlogArticlePolicy;
use App\Policies\BoosterApplicationPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        User::class => UserPolicy::class,
        BoosterApplication::class => BoosterApplicationPolicy::class,
        ContactMessage::class => ContactMessagePolicy::class,
        BlogArticle::class => BlogArticlePolicy::class,
        Promotion::class => PromotionPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
