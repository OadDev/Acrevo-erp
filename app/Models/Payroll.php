<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id', 'month', 'year', 'basic_salary', 'allowances', 'deductions',
        'advance_deducted', 'overtime_amount', 'incentive', 'net_salary', 'paid_amount',
        'status', 'paid_at', 'payslip_path', 'processed_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function remaining(): float
    {
        return (float) $this->net_salary - (float) $this->paid_amount;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class)->orderBy('paid_on');
    }
}
