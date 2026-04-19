<?php

namespace App\Providers;

use App\Contracts\BadgeEvaluator;
use App\Contracts\FileStorage;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsProvider;
use App\Contracts\TelegramGateway;
use App\Listeners\EnrollInEmailSequence;
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
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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

        Queue::before(function (JobProcessing $event) {
            if (! app()->bound('sentry')) {
                return;
            }

            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($event) {
                $scope->setTag('queue.name', $event->job->getQueue() ?? 'default');
                $scope->setTag('queue.job', $event->job->resolveName());

                $command = $event->job->payload()['data']['command'] ?? null;
                if (! is_string($command)) {
                    return;
                }

                try {
                    $instance = unserialize($command);
                } catch (\Throwable) {
                    return;
                }

                if (! is_object($instance)) {
                    return;
                }

                foreach (['community', 'user'] as $rel) {
                    if (isset($instance->{$rel}) && is_object($instance->{$rel}) && isset($instance->{$rel}->id)) {
                        $scope->setTag("{$rel}_id", (string) $instance->{$rel}->id);
                    }
                }
                foreach (['communityId', 'userId'] as $key) {
                    if (isset($instance->{$key})) {
                        $scope->setTag(strtolower(preg_replace('/([A-Z])/', '_$1', $key)), (string) $instance->{$key});
                    }
                }
            });
        });

        View::composer('app', function ($view) {
            if (! array_key_exists('ogMeta', $view->getData())) {
                $view->with('ogMeta', [
                    'title' => 'Curzzo — Build & monetize your community',
                    'description' => 'Create communities, sell courses, and launch AI bots on Curzzo.',
                    'image' => url('/brand/ICON/'.rawurlencode('CURZZO LOGO WHIT BG ROUND.png')),
                    'url' => url()->current(),
                ]);
            }
        });
    }
}
