<?php

namespace App\Console\Commands;

use App\Models\BillingSubmission;
use App\Support\VerifactuClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendPendingBillingRecords extends Command
{
    protected $signature = 'billing:send-pending';

    protected $description = 'Remite el siguiente registro VERI*FACTU pendiente al entorno configurado con certificado propio';

    public function handle(VerifactuClient $client): int
    {
        if (! config('billing.send_enabled')) {
            $this->components->info(__('billing.messages.sending_disabled'));

            return self::SUCCESS;
        }
        $environment = config('billing.environment', 'test');
        $lock = Cache::lock('billing:send-pending:'.$environment, 120);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            if (BillingSubmission::inEnvironment($environment)->where('retry_after', '>', now())->exists()) {
                return self::SUCCESS;
            }
            BillingSubmission::inEnvironment($environment)->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(5))->update(['status' => 'transport_error', 'claimed_at' => null]);
            $submission = BillingSubmission::inEnvironment($environment)->whereIn('status', ['pending', 'transport_error'])->orderBy('id')->first();
            if ($submission) {
                $client->send($submission);
                $this->components->info(__('billing.options.'.$submission->fresh()->status));
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
