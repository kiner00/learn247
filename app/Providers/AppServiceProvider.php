<?php

namespace App\Providers;

use App\Contracts\BadgeEvaluator;
use App\Contracts\FileStorage;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsProvider;
use App\Contracts\TelegramGateway;
use App\Listeners\EnrollInEmailSequence;
use App\Listeners\LogAiUsage;
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
use App\Models\User;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\ImageGenerated;

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

        Event::listen(AgentPrompted::class, [LogAiUsage::class, 'handleAgentPrompted']);
        Event::listen(ImageGenerated::class, [LogAiUsage::class, 'handleImageGenerated']);

        // Pulse dashboard access — super admins only.
        Gate::define('viewPulse', fn (User $user) => $user->isSuperAdmin());

        Queue::before(function (JobProcessing $event) {
            $ids = $this->extractJobIds($event);

            if (isset($ids['community_id'])) {
                Context::add('ai.community_id', $ids['community_id']);
            }
            if (isset($ids['user_id'])) {
                Context::add('ai.user_id', $ids['user_id']);
            }

            if (! app()->bound('sentry')) {
                return;
            }

            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($event, $ids) {
                $scope->setTag('queue.name', $event->job->getQueue() ?? 'default');
                $scope->setTag('queue.job', $event->job->resolveName());
                foreach ($ids as $key => $value) {
                    $scope->setTag($key, (string) $value);
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

    /**
     * @return array<string, int>
     */
    private function extractJobIds(JobProcessing $event): array
    {
        $command = $event->job->payload()['data']['command'] ?? null;
        if (! is_string($command)) {
            return [];
        }

        try {
            $instance = unserialize($command);
        } catch (\Throwable) {
            return [];
        }

        if (! is_object($instance)) {
            return [];
        }

        $ids = [];
        foreach (['community' => 'community_id', 'user' => 'user_id'] as $prop => $key) {
            if (isset($instance->{$prop}) && is_object($instance->{$prop}) && isset($instance->{$prop}->id)) {
                $ids[$key] = (int) $instance->{$prop}->id;
            }
        }
        foreach (['communityId' => 'community_id', 'userId' => 'user_id'] as $prop => $key) {
            if (isset($instance->{$prop}) && ! isset($ids[$key])) {
                $ids[$key] = (int) $instance->{$prop};
            }
        }

        return $ids;
    }
}
