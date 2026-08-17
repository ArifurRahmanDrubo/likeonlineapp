<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSetup extends Model
{
    use HasFactory;

    protected $table = 'email_setup';

    protected $guarded = [];

    protected $fillable = [
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'mail_from_name',
        'mail_from_email',
    ];
}
