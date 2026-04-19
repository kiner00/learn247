<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptCommunitySecrets extends Command
{
    protected $signature = 'communities:encrypt-secrets
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Re-encrypt plaintext values in communities.telegram_bot_token and resend_api_key (one-shot backfill after adding encrypted casts).';

    private const COLUMNS = ['telegram_bot_token', 'resend_api_key'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totals = ['encrypted' => 0, 'already' => 0, 'empty' => 0];

        DB::table('communities')
            ->select(array_merge(['id'], self::COLUMNS))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$totals, $dryRun) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach (self::COLUMNS as $col) {
                        $val = $row->{$col};
                        if ($val === null || $val === '') {
                            $totals['empty']++;

                            continue;
                        }
                        try {
                            Crypt::decryptString($val);
                            $totals['already']++;
                        } catch (DecryptException) {
                            $updates[$col] = Crypt::encryptString($val);
                            $totals['encrypted']++;
                        }
                    }
                    if ($updates && ! $dryRun) {
                        DB::table('communities')->where('id', $row->id)->update($updates);
                    }
                    if ($updates) {
                        $this->line(sprintf('  community #%d → %s', $row->id, implode(', ', array_keys($updates))));
                    }
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%s: encrypted=%d, already-encrypted=%d, empty=%d',
            $dryRun ? 'DRY RUN' : 'Done',
            $totals['encrypted'], $totals['already'], $totals['empty'],
        ));

        return self::SUCCESS;
    }
}
