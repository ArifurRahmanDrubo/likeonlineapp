<?php

namespace App\Jobs;

use App\Services\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Non-blocking automated SMS (payment confirmations, due reminders).
 *
 * Always dispatched onto the queue (see SmsAutomation) so a slow or failing
 * SMS gateway can never block or break the payment/settlement flow. Even on a
 * sync queue driver the send is wrapped in try/catch, so an API timeout is
 * logged and swallowed — never thrown back to the caller.
 */
class SendAutomatedSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $to;
    public $templateKey;
    public $variables;

    public function __construct(string $to, string $templateKey, array $variables = [])
    {
        $this->to = $to;
        $this->templateKey = $templateKey;
        $this->variables = $variables;
    }

    public function handle(SmsManager $smsManager)
    {
        try {
            if (empty($this->to)) {
                return;
            }

            // SmsManager::sendTemplateSms silently skips missing/disabled
            // templates and records every attempt in sms_logs; it never throws.
            $smsManager->sendTemplateSms($this->to, $this->templateKey, $this->variables);
        } catch (\Throwable $e) {
            Log::error("Automated SMS job failed (template {$this->templateKey}): {$e->getMessage()}");
        }
    }
}
