<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('system_permissions')->updateOrInsert(
            ['id' => 1],
            [
                'payroll_with_late_fees' => false,
                'payroll_with_overtime' => false,
                'payroll_with_absence' => false,
                'payment_status_wise_client_disabled' => false,
                'company_name_invoice' => false,
                'block_mikrotik_profile' => null,
                'save_comment_in_mikrotik' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}