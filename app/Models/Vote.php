<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $fillable = [
        'meeting_id',
        'candidate_id',
        'voter_koperasi_id',
        'voter_name',
        'device_fingerprint',
        'ip_address',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
