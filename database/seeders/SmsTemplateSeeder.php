<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Seed the default SMS templates.
     */
    public function run(): void
    {
        SmsTemplate::updateOrCreate(
            ['key' => 'new_customer_greeting'],
            [
                'title' => 'New Customer Greeting',
                'template' => 'Welcome {customer_name}! Your account is active. Username: {username}, Password: {password}. Thank you!',
                'placeholders' => ['{customer_name}', '{username}', '{password}', '{package}', '{company_name}'],
                'is_enabled' => true,
            ]
        );

        SmsTemplate::updateOrCreate(
            ['key' => 'bill_reminder'],
            [
                'title' => 'Bill Reminder',
                'template' => 'Dear {customer_name}, this is a reminder that your bill of {amount} Tk for {package} is due. Please pay before {due_date}. Thank you, {company_name}.',
                'placeholders' => ['{customer_name}', '{amount}', '{package}', '{due_date}', '{company_name}'],
                'is_enabled' => true,
            ]
        );

        SmsTemplate::updateOrCreate(
            ['key' => 'payment_confirmation'],
            [
                'title' => 'Payment Confirmation',
                'template' => 'Dear {customer_name}, we have received your payment of {amount} Tk. Invoice {invoice_no} is now paid. Thank you, {company_name}.',
                'placeholders' => ['{customer_name}', '{amount}', '{invoice_no}', '{company_name}'],
                'is_enabled' => true,
            ]
        );
    }
}
