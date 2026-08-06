<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPermission extends Model
{
    use HasFactory;
    protected $fillable = ['payroll_with_late_fees', 'payroll_with_overtime', 'payroll_with_absence', 'payment_status_wise_client_disabled', 'company_name_invoice', 'block_mikrotik_profile', 'save_comment_in_mikrotik'];
}
