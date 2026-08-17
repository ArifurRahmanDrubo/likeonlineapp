<?php

namespace App\Services;

use App\Models\SmsGateway;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsManager
{
    /**
     * Send a templated SMS. When the template does not exist or is disabled
     * the send is skipped entirely (no log entry, no error).
     *
     * @return \App\Models\SmsLog|null
     */
    public function sendTemplateSms(string $to, string $templateKey, array $variables = []): ?SmsLog
    {
        $template = SmsTemplate::where('key', $templateKey)->first();

        if (!$template || !$template->is_enabled) {
            return null;
        }

        $message = $this->replacePlaceholders($template->template, $variables);

        return $this->sendRawSms($to, $message, $templateKey);
    }

    /**
     * Send a raw SMS through the currently active gateway and always record
     * the attempt (success or failure) in the sms_logs table.
     */
    public function sendRawSms(string $to, string $message, ?string $templateKey = null): SmsLog
    {
        $gateway = SmsGateway::where('is_active', true)->first();

        // No active gateway, or an active gateway without an API key yet.
        if (!$gateway || !$gateway->api_key) {
            return SmsLog::create([
                'recipient' => $to,
                'message' => $message,
                'provider' => $gateway ? $gateway->name : null,
                'template_key' => $templateKey,
                'status' => 'failed',
                'response' => 'No Active Gateway Configured',
            ]);
        }

        $recipient = $this->normalizeNumber($to);

        try {
            $response = $this->dispatch($gateway, $recipient, $message);
            $status = $this->isSuccess($gateway->provider, $response) ? 'success' : 'failed';
        } catch (\Throwable $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            $response = 'Exception: ' . $e->getMessage();
            $status = 'failed';
        }

        return SmsLog::create([
            'recipient' => $recipient,
            'message' => $message,
            'provider' => $gateway->name,
            'template_key' => $templateKey,
            'status' => $status,
            'response' => $response,
        ]);
    }

    /**
     * Replace {placeholder} tokens with the supplied variables.
     */
    protected function replacePlaceholders(string $template, array $variables): string
    {
        $search = [];
        $replace = [];
        foreach ($variables as $key => $value) {
            $search[] = '{' . $key . '}';
            $replace[] = (string) ($value ?? '');
        }

        return str_replace($search, $replace, $template);
    }

    /**
     * Normalize a Bangladeshi mobile number to the 8801XXXXXXXXX format
     * expected by both gateway APIs.
     */
    protected function normalizeNumber(string $to): string
    {
        $to = trim($to);
        $to = ltrim($to, '+');

        if (str_starts_with($to, '88')) {
            return $to;
        }

        if (str_starts_with($to, '01')) {
            return '88' . $to;
        }

        return $to;
    }

    /**
     * Route the message to the correct provider API and return the raw body.
     */
    protected function dispatch(SmsGateway $gateway, string $to, string $message): string
    {
        switch ($gateway->provider) {
            case 'sms_net_bd':
                return $this->sendViaSmsNetBd($gateway, $to, $message);
            case 'bulksms_bd':
                return $this->sendViaBulkSmsBd($gateway, $to, $message);
            default:
                throw new \RuntimeException("Unknown SMS provider: {$gateway->provider}");
        }
    }

    /**
     * SMS.net.bd — POST https://api.sms.net.bd/sendsms
     * Params: api_key, msg, to, sender_id (optional)
     */
    protected function sendViaSmsNetBd(SmsGateway $gateway, string $to, string $message): string
    {
        $params = [
            'api_key' => $gateway->api_key,
            'msg' => $message,
            'to' => $to,
        ];

        if ($gateway->sender_id) {
            $params['sender_id'] = $gateway->sender_id;
        }

        $response = Http::asForm()->post('https://api.sms.net.bd/sendsms', $params);

        return $response->body();
    }

    /**
     * BulkSMS BD — POST https://bulksmsbd.net/api/smsapi
     * Params: api_key, senderid, number, message
     */
    protected function sendViaBulkSmsBd(SmsGateway $gateway, string $to, string $message): string
    {
        $params = [
            'api_key' => $gateway->api_key,
            'senderid' => $gateway->sender_id,
            'number' => $to,
            'message' => $message,
        ];

        $response = Http::asForm()->post('https://bulksmsbd.net/api/smsapi', $params);

        return $response->body();
    }

    /**
     * Determine success from the raw gateway response body.
     */
    protected function isSuccess(string $provider, string $body): bool
    {
        if ($provider === 'sms_net_bd') {
            // {"error": 0, "msg": "Request successfully submitted"} → success
            $decoded = json_decode($body, true);
            if (is_array($decoded) && array_key_exists('error', $decoded)) {
                return (int) $decoded['error'] === 0;
            }

            return false;
        }

        if ($provider === 'bulksms_bd') {
            // Success response code 202 → "SMS Submitted Successfully"
            return str_contains($body, '202');
        }

        return false;
    }
}
