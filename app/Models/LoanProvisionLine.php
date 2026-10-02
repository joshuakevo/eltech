<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProvisionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_provision_id', 'loan_id', 'client_id', 'days_in_arrears', 'oldest_arrears_date',
        'outstanding_principal', 'rate', 'provision_amount',
    ];

    protected $casts = [
        'outstanding_principal' => 'float',
        'rate'                  => 'float',
        'provision_amount'      => 'float',
    ];

    public function provision()
    {
        return $this->belongsTo(LoanProvision::class, 'loan_provision_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
