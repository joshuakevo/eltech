<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'phone', 'message', 'category', 'status', 'gateway', 'error', 'sent_by',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
