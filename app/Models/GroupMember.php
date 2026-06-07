<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id', 'client_id', 'name', 'phone', 'email', 'national_id',
        'balance', 'is_leader', 'user_id', 'status', 'created_by',
    ];

    protected $casts = [
        'group_id'  => 'integer',
        'balance'   => 'decimal:2',
        'is_leader' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(GroupTransaction::class, 'member_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
