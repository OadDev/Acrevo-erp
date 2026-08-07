<?php

namespace App\Exports;

use App\Models\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PayrollExport implements FromCollection, WithHeadings
{
    public function __construct(private int $month, private int $year) {}

    public function collection(): Collection
    {
        return Payroll::with('employee')
            ->where('month', $this->month)->where('year', $this->year)
            ->get()
            ->map(fn (Payroll $payroll) => [
                $payroll->employee->employee_code,
                $payroll->employee->name,
                $payroll->basic_salary,
                $payroll->allowances,
                $payroll->deductions,
                $payroll->net_salary,
                $payroll->status,
            ]);
    }

    public function headings(): array
    {
        return ['Employee Code', 'Name', 'Basic', 'Allowances', 'Deductions', 'Net Salary', 'Status'];
    }
}
