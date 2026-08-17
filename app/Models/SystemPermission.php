<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPermission extends Model
{
    use HasFactory;
    protected $fillable = ['payroll_with_late_fees', 'payroll_with_overtime', 'payroll_with_absence', 'payment_status_wise_client_disabled', 'company_name_invoice', 'block_mikrotik_profile', 'save_comment_in_mikrotik'];

    /**
     * Resolve a system_permissions flag.
     *
     * The settings UI stores these columns as 'enable' / 'disable' strings.
     * When the column is unset (NULL / empty) the given $default applies, so
     * callers can express per-feature defaults (e.g. payroll features default
     * to ON while auto-disabling defaults to OFF).
     */
    public function isEnabled(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $this->getAttributes())) {
            return $default;
        }

        $value = $this->{$key};
        if ($value === null || $value === '') {
            return $default;
        }

        return strtolower((string) $value) === 'enable';
    }
}
