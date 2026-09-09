<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompletionCertificate extends Model
{
    use HasSequenceNumber, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $sequencePrefix = 'CC';

    protected $sequenceColumn = 'certificate_no';

    protected $fillable = ['work_order_id', 'certificate_no', 'issued_date', 'issued_by', 'pdf_path', 'client_signed_at'];

    protected function casts(): array
    {
        return ['issued_date' => 'date', 'client_signed_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
