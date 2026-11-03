<?php

namespace App\Jobs;

use App\Models\BillingSubmission;
use App\Support\VerifactuClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubmitBillingRecord implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $submissionId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(VerifactuClient $client): void
    {
        $submission = BillingSubmission::findOrFail($this->submissionId);
        $client->send($submission);
        if ($submission->fresh()->status === 'transport_error') {
            $this->release(60);
        }
    }
}
