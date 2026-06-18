<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PollVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_option_id',
        'user_id',
    ];

    public function pollOption()
    {
        return $this->belongsTo(PollOption::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
