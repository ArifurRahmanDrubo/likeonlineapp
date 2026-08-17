<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailSetupSeeder extends Seeder
{
    /**
     * Seed default dummy/sandbox SMTP settings (Mailtrap) so the app can send
     * mail immediately and the Email Setup page has data to display.
     */
    public function run(): void
    {
        DB::table('email_setup')->updateOrInsert(
            ['id' => 1],
            [
                'mailer' => 'smtp',
                'host' => 'sandbox.smtp.mailtrap.io',
                'port' => 2525,
                'username' => 'your-mailtrap-username',
                'password' => 'your-mailtrap-password',
                'encryption' => 'TLS',
                'mail_from_name' => 'ISP Provider',
                'mail_from_email' => 'no-reply@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
