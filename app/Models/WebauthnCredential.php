<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A passkey (fingerprint / face / Windows Hello) registered by a user on one device. */
class WebauthnCredential extends Model
{
    protected $fillable = ['user_id', 'credential_id', 'public_key', 'alg', 'sign_count', 'name', 'last_used_at'];

    protected $hidden = ['public_key'];

    protected $casts = ['last_used_at' => 'datetime', 'sign_count' => 'integer', 'alg' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
