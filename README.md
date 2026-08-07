# Acrevo ERP — Role-Based Work Order Management System

A Laravel 12 ERP that runs a service company's entire operation through a single
centralized **Work Order** workflow: Enquiry → Site Visit → Quotation → Client
Approval → Work Order → HR Assignment → Executive Team → Daily
Checklist/Progress/Media/Materials/Labour/Measurement Book/Ledger → QC → Client
Review → Ticket (if needed) → Re-Work → Final QC → Completion → Client
Feedback → Next Work Order.

Every department reads and writes the same Work Order record; role
permissions decide which screens, buttons, and actions each user sees.

## Stack

- Laravel 12 / PHP 8.3+, Blade + Tailwind + AlpineJS (dark mode via `class` strategy)
- Spatie Permission (RBAC), Spatie Activitylog, Spatie MediaLibrary
- Laravel Sanctum (token API for the future Flutter app), Laravel Excel, DomPDF

## Setup

```bash
composer install
npm install && npm run build   # or `npm run dev` while working on the UI
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

`DB_CONNECTION` defaults to SQLite for local development. Switch it (and the
commented `DB_HOST`/`DB_DATABASE`/etc. block) to MySQL for anything beyond a
laptop — the schema, migrations, and queries are MySQL-clean.

The seeder creates the 13 default roles (Admin, Sales, Marketing, HR,
Executive Team Leader, Executive Team Member, QC Officer, Finance,
Management, Legal, Auditor, Sub Contractor, Client) with a permission set
scoped per role, and one admin login:

```
admin@acrevo.test / password
```

Admin creates all other users from **Administration → Users**, which
generates a temporary password shown once at creation time
(`must_change_password` is set so this is ready to wire into a forced
password-change flow).

## Architecture notes

- **`app/Models/WorkOrder.php`** is the workflow engine: `transitionTo()`
  moves the status and writes a `work_order_status_logs` row; a
  `WorkOrderStatusChanged` event + `NotifyWorkOrderStakeholders` listener
  fan out notifications on the transitions that matter.
- **`app/Models/Concerns/HasSequenceNumber.php`** generates the
  human-readable document numbers (`WO-202608-0001`, `ENQ-...`, `QT-...`,
  `TKT-...`, `EMP-...`, `CL-...`, `MB-...`, `INV-...`, `CC-...`).
- **Permissions** are plain strings (`work_orders.view`, `qc.perform`, …)
  seeded in `database/seeders/RolePermissionSeeder.php` and checked with
  `@can` / `$user->can()` — Spatie registers these as gates automatically.
  `app/Policies/WorkOrderPolicy.php` and `TicketPolicy.php` add
  object-level scoping on top (an Executive Team member can only see work
  orders their team is assigned to; a Client can only see their own).
- **`routes/modules.php`** groups every module's routes behind its
  permission middleware; `routes/api.php` + `routes/api_v1.php` hold the
  Sanctum-protected REST API.
- Work order execution (checklist, progress, media, material/labour
  entries, measurement book, ledger) all live as tabs on one Work Order
  detail page (`resources/views/work-orders/show.blade.php` +
  `work-orders/tabs/*`) so every department is looking at the same record.

## What's built

Sales & Marketing (Enquiry → Site Visit → Quotation → Work Order), HR
(Workers, Attendance, Payroll, Executive Teams), Executive Team execution,
QC, Tickets, Finance/Legal/Audit/Company Records, Reports (Excel export),
Admin (Users, dynamic Role/Permission editor, Activity Logs), Global
Search, and a scoped Client Portal. Verified end-to-end via a full
workflow run (enquiry through completion, invoicing, client feedback) and
an HTTP smoke test of every route.

## Natural next steps

Media Library browser UI, in-app/browser notification center, Company
Settings screen, Masters management, Kanban/Calendar views, Chart.js /
ApexCharts dashboards, WhatsApp/SMS notification channels, 2FA
activation flow, session-timeout enforcement, ticket file attachments,
quotation email delivery, and broader REST API coverage for the Flutter
app beyond the current `work-orders` endpoints.
