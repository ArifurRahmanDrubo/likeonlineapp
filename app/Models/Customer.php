<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
  protected $guarded = []; 
    protected $appends = ['formatted_id'];

    protected $casts = [
        'left_date' => 'datetime',
    ];

    /**
     * Store the monthly bill as a whole number (nearest taka) on every write
     * — create, edit, package change — so generated bills never carry
     * decimals or floating-point drift.
     */
    public function setMonthlybillAttribute($value)
    {
        $this->attributes['monthlybill'] = $value === null || $value === '' ? null : round((float) $value);
    }

    // public function getFormattedIdAttribute()
    // {
    //     return str_pad($this->attributes['id'], 4, '0', STR_PAD_LEFT);
    // }

    public function getFormattedIdAttribute()
{
    return str_pad((string) ($this->id ?? 0), 4, '0', STR_PAD_LEFT);
}

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function payment()
    {
        return $this->hasMany(Payment::class);
    }

    public function generatedBill()
    {
        return $this->hasMany(GeneratedBill::class);
    }

    public function statusChanged()
    {
        return $this->hasMany(StatusChanged::class);
    }

    public function packageChanged()
    {
        return $this->hasMany(PackageChanged::class);
    }

public function server()
{
    return $this->belongsTo(MikrotikServer::class, 'server_id', 'id');
}

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($customer) {
            // Delete related invoice, payment, generated bill, etc.
            if ($customer->invoice) {
                $customer->invoice()->delete();
            }
            if ($customer->payment) {
                $customer->payment()->delete();
            }
            if ($customer->generatedBill) {
                $customer->generatedBill()->delete();
            }
            if ($customer->statusChanged) {
                $customer->statusChanged()->delete();
            }
            if ($customer->packageChanged) {
                $customer->packageChanged()->delete();
            }
            // If the customer has an associated user, delete it and all related user data
            if ($customer->user) {
                $customer->user->delete();
            }
        });
    }
}
