<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'address', 'phone', 'email', 'manager_name', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function users()       { return $this->hasMany(User::class); }
    public function clients()     { return $this->hasMany(Client::class); }
    public function loans()       { return $this->hasMany(Loan::class); }
    public function savingsAccounts() { return $this->hasMany(SavingsAccount::class); }
    public function fixedDeposits()   { return $this->hasMany(FixedDeposit::class); }
}
