<?php

namespace Database\Seeders;

use App\Models\SmsGateway;
use Illuminate\Database\Seeder;

class SmsGatewaySeeder extends Seeder
{
    /**
     * Seed the SMS gateway providers.
     */
    public function run(): void
    {
        SmsGateway::updateOrCreate(
            ['provider' => 'sms_net_bd'],
            ['name' => 'SMS.net.bd', 'is_active' => true]
        );

        SmsGateway::updateOrCreate(
            ['provider' => 'bulksms_bd'],
            ['name' => 'BulkSMS BD', 'is_active' => false]
        );
    }
}
