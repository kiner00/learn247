<?php

namespace App\Actions\Curzzo;

use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\User;
use Illuminate\Support\Collection;

class GetCurzzoChatHistory
{
    /**
     * Returns the user's last 100 messages with this Curzzo, oldest first.
     *
     * @return Collection<int, array{id: int, role: string, text: string}>
     */
    public function execute(User $user, Curzzo $curzzo): Collection
    {
        return CurzzoMessage::where('curzzo_id', $curzzo->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->select('id', 'role', 'content')
            ->limit(100)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'text' => $m->content,
            ]);
    }
}
