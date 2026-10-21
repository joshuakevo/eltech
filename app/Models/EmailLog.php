<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = ['client_id', 'email', 'subject', 'body', 'attachment_name', 'category', 'status', 'error', 'sent_by'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
