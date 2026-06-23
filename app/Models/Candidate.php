<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = [
        'meeting_id',
        'koperasi_id',
        'name',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
