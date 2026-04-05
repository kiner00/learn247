<?php

namespace App\Events;

use App\Models\CommunityMember;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CommunityMember $member,
        public readonly Subscription $subscription,
    ) {}
}
