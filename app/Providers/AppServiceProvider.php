<?php

namespace App\Providers;

use App\Contracts\BadgeEvaluator;
use App\Contracts\FileStorage;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsProvider;
use App\Contracts\TelegramGateway;
use App\Models\Comment;
use App\Models\LessonCompletion;
use App\Models\Post;
use App\Observers\CommentObserver;
use App\Observers\LessonCompletionObserver;
use App\Observers\PostObserver;
use App\Services\BadgeService;
use App\Services\Sms\SmsDispatcher;
use App\Services\StorageService;
use App\Services\TelegramService;
use App\Services\XenditService;
use App\Listeners\EnrollInEmailSequence;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, XenditService::class);
        $this->app->bind(SmsProvider::class, SmsDispatcher::class);
        $this->app->bind(FileStorage::class, StorageService::class);
        $this->app->bind(TelegramGateway::class, TelegramService::class);
        $this->app->bind(BadgeEvaluator::class, BadgeService::class);
    }

    public function boot(): void
    {
        Post::observe(PostObserver::class);
        Comment::observe(CommentObserver::class);
        LessonCompletion::observe(LessonCompletionObserver::class);

        Event::subscribe(EnrollInEmailSequence::class);

        View::composer('app', function ($view) {
            if (! array_key_exists('ogMeta', $view->getData())) {
                $view->with('ogMeta', [
                    'title'       => 'Curzzo — Build & monetize your community',
                    'description' => 'Create communities, sell courses, and launch AI bots on Curzzo.',
                    'image'       => url('/brand/ICON/' . rawurlencode('CURZZO LOGO WHIT BG ROUND.png')),
                    'url'         => url()->current(),
                ]);
            }
        });
    }
}
