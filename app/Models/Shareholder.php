<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shareholder extends Model
{
    protected $fillable = [
        'name',
        'koperasi_id',
        'phone_number',
        'email',
        'address',
    ];
}
