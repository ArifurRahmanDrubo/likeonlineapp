<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = ['mac_reseller_id', 'label', 'value', 'checked', 'parent_id'];

    public function macReseller()
    {
        return $this->belongsTo(MacReseller::class);
    }

    // Get child menus
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    // Get parent menu
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }
}
