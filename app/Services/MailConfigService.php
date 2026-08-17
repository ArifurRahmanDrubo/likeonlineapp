<?php

namespace App\Services;

use App\Models\EmailSetup;

class MailConfigService
{
    /**
     * Apply the database-driven mail configuration to Laravel's runtime mail
     * config. Call this before dispatching any email so the SMTP credentials
     * managed from the Email Setup page are used instead of the .env values.
     *
     * Falls back silently to the existing config when no settings row exists.
     */
    public static function apply(): void
    {
        $mailConfig = EmailSetup::query()->first();

        if (!$mailConfig) {
            return;
        }

        $encryption = strtolower((string) $mailConfig->encryption);
        if (!in_array($encryption, ['ssl', 'tls'], true)) {
            $encryption = null;
        }

        config([
            'mail.default' => $mailConfig->mailer ?? 'smtp',
            'mail.mailers.smtp.host' => $mailConfig->host,
            'mail.mailers.smtp.port' => $mailConfig->port,
            'mail.mailers.smtp.username' => $mailConfig->username,
            'mail.mailers.smtp.password' => $mailConfig->password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $mailConfig->mail_from_email,
            'mail.from.name' => $mailConfig->mail_from_name,
        ]);
    }
}
