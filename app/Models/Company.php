<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Company extends Model
{
    use LogsActivity;

    public const TAX_REGIMES = ['gst', 'vat'];

    /**
     * Currency symbol shown on PDFs, keyed by currency code - falls back
     * to the currency code itself (e.g. "AED") for one not listed here.
     */
    public const CURRENCY_SYMBOLS = [
        'INR' => 'Rs.',
        'OMR' => 'OMR',
        'AED' => 'AED',
        'USD' => '$',
    ];

    /**
     * [currency name, subunit name] used to build the "Amount Chargeable
     * (in words)" line on document PDFs - see NumberToWords::amountInWords().
     */
    public const CURRENCY_WORDS = [
        'INR' => ['Indian Rupee', 'Paise'],
        'OMR' => ['Omani Rial', 'Baisa'],
        'AED' => ['UAE Dirham', 'Fils'],
        'USD' => ['US Dollar', 'Cent'],
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name', 'code', 'legal_name', 'logo_path', 'email', 'phone', 'address',
        'city', 'state', 'pincode', 'country', 'gstin', 'vatin', 'tax_regime', 'pan', 'website',
        'currency', 'timezone', 'settings',
        'bank_name', 'bank_account_name', 'bank_account_no', 'bank_ifsc_code', 'bank_swift_code', 'bank_branch',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * "GSTIN" for a GST-regime company, "VATIN" for a VAT-regime one -
     * drives the label printed on document PDFs.
     */
    public function taxIdLabel(): string
    {
        return $this->tax_regime === 'vat' ? 'VATIN' : 'GSTIN';
    }

    public function taxId(): ?string
    {
        return $this->tax_regime === 'vat' ? $this->vatin : $this->gstin;
    }

    public function taxLabel(): string
    {
        return $this->tax_regime === 'vat' ? 'VAT' : 'GST';
    }

    public function currencySymbol(): string
    {
        return self::CURRENCY_SYMBOLS[$this->currency] ?? $this->currency;
    }

    public function amountInWords(float $amount): string
    {
        [$name, $subunit] = self::CURRENCY_WORDS[$this->currency] ?? [$this->currency, null];

        return \App\Support\NumberToWords::amountInWords($amount, $name, $subunit);
    }

    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class);
    }

    public function taxInvoices(): HasMany
    {
        return $this->hasMany(TaxInvoice::class);
    }

    public function deliveryChallans(): HasMany
    {
        return $this->hasMany(DeliveryChallan::class);
    }
}
