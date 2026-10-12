<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Proforma Invoice / Tax Invoice / Delivery Challan /
     * Company Profile / Item catalog permissions directly to existing
     * production roles (rather than re-running the full seeder, which
     * would wipe any role customization already made in production).
     * Mirrors RolePermissionSeeder's grants for Admin (all, via '*'
     * already)/Sales/Finance/Management exactly.
     */
    public function up(): void
    {
        $all = [
            'companies.manage', 'items.manage',
            'proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit',
            'proforma_invoices.delete', 'proforma_invoices.convert', 'proforma_invoices.download_pdf',
            'tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit',
            'tax_invoices.delete', 'tax_invoices.download_pdf',
            'delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit',
            'delivery_challans.delete', 'delivery_challans.download_pdf',
        ];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        $sales = Role::where('name', 'Sales')->where('guard_name', 'web')->first();
        if ($sales) {
            $sales->givePermissionTo([
                'proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit', 'proforma_invoices.convert', 'proforma_invoices.download_pdf',
                'tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit', 'tax_invoices.download_pdf',
                'delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit', 'delivery_challans.download_pdf',
            ]);
        }

        $finance = Role::where('name', 'Finance')->where('guard_name', 'web')->first();
        if ($finance) {
            $finance->givePermissionTo([
                'items.manage',
                'proforma_invoices.view', 'proforma_invoices.download_pdf',
                'tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit', 'tax_invoices.delete', 'tax_invoices.download_pdf',
                'delivery_challans.view', 'delivery_challans.download_pdf',
            ]);
        }

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo([
                'proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit', 'proforma_invoices.convert', 'proforma_invoices.download_pdf',
                'tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit', 'tax_invoices.download_pdf',
                'delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit', 'delivery_challans.download_pdf',
            ]);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo([
                'companies.manage', 'items.manage',
                'proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit',
                'proforma_invoices.delete', 'proforma_invoices.convert', 'proforma_invoices.download_pdf',
                'tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit',
                'tax_invoices.delete', 'tax_invoices.download_pdf',
                'delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit',
                'delivery_challans.delete', 'delivery_challans.download_pdf',
            ]);
        });
    }
};
