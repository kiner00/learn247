<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\LessonCompletion;
use App\Models\Post;
use App\Observers\CommentObserver;
use App\Observers\LessonCompletionObserver;
use App\Observers\PostObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Post::observe(PostObserver::class);
        Comment::observe(CommentObserver::class);
        LessonCompletion::observe(LessonCompletionObserver::class);
    }
}
