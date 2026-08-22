<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TaskScheduleController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\SubcontractorController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyRecordController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\EnquiryFollowUpController;
use App\Http\Controllers\ExecutiveTeamController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\MyPayrollController;
use App\Http\Controllers\MyWorkOrderController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\Portal\PortalApprovalRequestController;
use App\Http\Controllers\Portal\PortalInvoiceController;
use App\Http\Controllers\Portal\PortalQuotationController;
use App\Http\Controllers\Portal\PortalTicketController;
use App\Http\Controllers\Portal\PortalWorkOrderController;
use App\Http\Controllers\QcInspectionController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteDocumentController;
use App\Http\Controllers\SiteVisitController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkOrder\ApprovalRequestController;
use App\Http\Controllers\WorkOrder\CompanyLedgerController;
use App\Http\Controllers\WorkOrder\DailyChecklistController;
use App\Http\Controllers\WorkOrder\DailyChecklistItemController;
use App\Http\Controllers\WorkOrder\DailyProgressController;
use App\Http\Controllers\WorkOrder\LabourEntryController;
use App\Http\Controllers\WorkOrder\LedgerController;
use App\Http\Controllers\WorkOrder\MaterialEntryController;
use App\Http\Controllers\WorkOrder\MaterialUsageEntryController;
use App\Http\Controllers\WorkOrder\MeasurementBookController;
use App\Http\Controllers\WorkOrder\MeasurementBookItemController;
use App\Http\Controllers\WorkOrder\WorkOrderAttendanceController;
use App\Http\Controllers\WorkOrder\WorkOrderMediaController;
use App\Http\Controllers\WorkOrder\WorkOrderPdfController;
use App\Http\Controllers\WorkOrder\WorkOrderZipController;
use App\Http\Controllers\WorkOrder\WorkOrderSummaryController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales & Marketing
|--------------------------------------------------------------------------
*/
Route::middleware('permission:enquiries.view')->group(function () {
    Route::resource('enquiries', EnquiryController::class);
    Route::post('enquiries/{enquiry}/follow-ups', [EnquiryFollowUpController::class, 'store'])->name('enquiries.follow-ups.store');
});

Route::middleware('permission:site_visits.view')->group(function () {
    Route::resource('site-visits', SiteVisitController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('site-visits/{siteVisit}/complete', [SiteVisitController::class, 'complete'])->name('site-visits.complete');
});

Route::middleware('permission:quotations.view')->group(function () {
    Route::resource('quotations', QuotationController::class);
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
});

Route::middleware('permission:enquiries.view')->group(function () {
    Route::resource('clients', ClientController::class);
    Route::post('clients/{client}/portal-access', [ClientController::class, 'generatePortalAccess'])->name('clients.portal-access');
    Route::put('clients/{client}/portal-permissions', [ClientController::class, 'updatePortalPermissions'])->name('clients.portal-permissions');
});

// The literal /sites/create path must be registered before the /sites/{site}
// wildcard below, or Laravel's route matching binds "create" to {site} first.
Route::middleware('permission:work_orders.edit')->group(function () {
    Route::resource('sites', SiteController::class)->only(['create', 'store', 'update', 'destroy']);
    Route::post('sites/{site}/complete', [SiteController::class, 'complete'])->name('sites.complete');
    Route::post('sites/{site}/documents', [SiteDocumentController::class, 'store'])->name('sites.documents.store');
    Route::delete('sites/{site}/documents/{media}', [SiteDocumentController::class, 'destroy'])->name('sites.documents.destroy');
});

Route::middleware('permission:work_orders.view')->group(function () {
    Route::resource('sites', SiteController::class)->only(['index', 'show']);
    Route::get('sites/{site}/pdf', [SiteController::class, 'pdf'])->name('sites.pdf');
    Route::get('sites/{site}/zip', [SiteController::class, 'zip'])->name('sites.zip');
});

Route::middleware('permission:work_orders.view')->group(function () {
    Route::get('work-orders/completed', [WorkOrderController::class, 'completed'])->name('work-orders.completed');
    Route::resource('work-orders', WorkOrderController::class)->except(['destroy', 'show'])->parameters(['work-orders' => 'workOrder']);
    Route::post('work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->name('work-orders.cancel');
    Route::post('work-orders/{workOrder}/rework', [WorkOrderController::class, 'createRework'])->name('work-orders.rework');
    Route::post('work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])->name('work-orders.complete');
    Route::delete('work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
});

// Feedback is submitted by the client who owns the work order, so it is
// authorized via WorkOrderPolicy rather than the work_orders.view permission.
Route::middleware('permission:work_orders.view|client_portal.access')->group(function () {
    Route::post('work-orders/{workOrder}/feedback', [WorkOrderController::class, 'storeFeedback'])->name('work-orders.feedback');
});

// Viewing a single work order is authorized by WorkOrderPolicy (covers sales,
// management, the assigned executive team, and the owning client), not by a
// single module permission — so it stays outside the permission-gated group
// above. Registered last so the static segments (create/completed/{id}/edit)
// still win over this catch-all {workOrder} wildcard.
Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');

// PDF exports share the same WorkOrderPolicy 'view' authorization as the
// show route above, checked inside WorkOrderPdfController itself.
Route::get('work-orders/{workOrder}/pdf', [WorkOrderPdfController::class, 'full'])->name('work-orders.pdf');
Route::get('work-orders/{workOrder}/pdf/{section}', [WorkOrderPdfController::class, 'section'])->name('work-orders.pdf.section');
Route::get('work-orders/{workOrder}/zip', WorkOrderZipController::class)->name('work-orders.zip');

Route::middleware('permission:worker_assignment.manage|work_orders.edit')->group(function () {
    Route::post('work-orders/{workOrder}/assign-team', [WorkOrderController::class, 'assignTeam'])->name('work-orders.assign-team');
    Route::delete('work-orders/{workOrder}/unassign-team/{assignment}', [WorkOrderController::class, 'unassignTeam'])->name('work-orders.unassign-team');
    Route::post('work-orders/{workOrder}/assign-sub-contractor', [WorkOrderController::class, 'assignSubContractor'])->name('work-orders.assign-sub-contractor');
    Route::delete('work-orders/{workOrder}/unassign-sub-contractor/{assignment}', [WorkOrderController::class, 'unassignSubContractor'])->name('work-orders.unassign-sub-contractor');
});

Route::middleware('permission:work_orders.edit')->group(function () {
    Route::patch('work-orders/{workOrder}/site', [WorkOrderController::class, 'updateSite'])->name('work-orders.site.update');
});

Route::middleware('permission:daily_progress.manage|work_orders.edit')->group(function () {
    Route::post('work-orders/{workOrder}/submit-for-qc', [WorkOrderController::class, 'submitForQc'])->name('work-orders.submit-for-qc');
});

/*
|--------------------------------------------------------------------------
| Executive Team execution (shared: sales can view, executive can edit)
|--------------------------------------------------------------------------
*/
Route::prefix('work-orders/{workOrder}')->name('work-orders.')->group(function () {
    Route::middleware('permission:daily_checklist.manage')->group(function () {
        Route::get('checklists', [DailyChecklistController::class, 'index'])->name('checklists.index');
        Route::post('checklists', [DailyChecklistController::class, 'store'])->name('checklists.store');
        Route::put('checklists/{checklist}', [DailyChecklistController::class, 'update'])->name('checklists.update');
        Route::delete('checklists/{checklist}', [DailyChecklistController::class, 'destroy'])->name('checklists.destroy');
        Route::post('checklist-items/{item}/done', [DailyChecklistItemController::class, 'markDone'])->name('checklist-items.done');
        Route::delete('checklist-items/{item}', [DailyChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');
    });
    Route::middleware('permission:daily_progress.manage')->group(function () {
        Route::get('progress', [DailyProgressController::class, 'index'])->name('progress.index');
        Route::post('progress', [DailyProgressController::class, 'store'])->name('progress.store');
        Route::put('progress/{report}', [DailyProgressController::class, 'update'])->name('progress.update');
        Route::delete('progress/{report}', [DailyProgressController::class, 'destroy'])->name('progress.destroy');
    });
    Route::middleware('permission:media.upload')->group(function () {
        Route::post('media', [WorkOrderMediaController::class, 'store'])->name('media.store');
        Route::delete('media/{media}', [WorkOrderMediaController::class, 'destroy'])->name('media.destroy');
    });
    Route::middleware('permission:site_records.manage')->group(function () {
        Route::get('materials', [MaterialEntryController::class, 'index'])->name('materials.index');
        Route::post('materials', [MaterialEntryController::class, 'store'])->name('materials.store');
        Route::put('materials/{material}', [MaterialEntryController::class, 'update'])->name('materials.update');
        Route::delete('materials/{material}', [MaterialEntryController::class, 'destroy'])->name('materials.destroy');
        Route::post('material-usage', [MaterialUsageEntryController::class, 'store'])->name('material-usage.store');
        Route::put('material-usage/{usage}', [MaterialUsageEntryController::class, 'update'])->name('material-usage.update');
        Route::delete('material-usage/{usage}', [MaterialUsageEntryController::class, 'destroy'])->name('material-usage.destroy');
        Route::get('labour', [LabourEntryController::class, 'index'])->name('labour.index');
        Route::post('labour', [LabourEntryController::class, 'store'])->name('labour.store');
        Route::put('labour/{labour}', [LabourEntryController::class, 'update'])->name('labour.update');
        Route::delete('labour/{labour}', [LabourEntryController::class, 'destroy'])->name('labour.destroy');
        Route::get('measurement-books', [MeasurementBookController::class, 'index'])->name('measurement-books.index');
        Route::post('measurement-books', [MeasurementBookController::class, 'store'])->name('measurement-books.store');
        Route::put('measurement-books/{measurementBook}', [MeasurementBookController::class, 'update'])->name('measurement-books.update');
        Route::delete('measurement-books/{measurementBook}', [MeasurementBookController::class, 'destroy'])->name('measurement-books.destroy');
        Route::post('measurement-books/{measurementBook}/items', [MeasurementBookItemController::class, 'store'])->name('measurement-books.items.store');
        Route::put('measurement-books/{measurementBook}/items/{item}', [MeasurementBookItemController::class, 'update'])->name('measurement-books.items.update');
        Route::delete('measurement-books/{measurementBook}/items/{item}', [MeasurementBookItemController::class, 'destroy'])->name('measurement-books.items.destroy');
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::post('ledger', [LedgerController::class, 'store'])->name('ledger.store');
        Route::put('ledger/{ledger}', [LedgerController::class, 'update'])->name('ledger.update');
        Route::delete('ledger/{ledger}', [LedgerController::class, 'destroy'])->name('ledger.destroy');
        Route::get('ledger/export', [LedgerController::class, 'export'])->name('ledger.export');
        Route::post('attendance', [WorkOrderAttendanceController::class, 'store'])->name('attendance.store');
    });
    // Company Ledger tracks the company's own expenses against a work order
    // and is restricted to Finance and Admin only (enforced in the
    // controller) - it deliberately has no permission:* middleware here,
    // since Finance doesn't hold the site_records.manage permission the
    // other WO entry routes use.
    Route::post('company-ledger', [CompanyLedgerController::class, 'store'])->name('company-ledger.store');
    Route::put('company-ledger/{companyLedger}', [CompanyLedgerController::class, 'update'])->name('company-ledger.update');
    Route::delete('company-ledger/{companyLedger}', [CompanyLedgerController::class, 'destroy'])->name('company-ledger.destroy');
    Route::get('company-ledger/export', [CompanyLedgerController::class, 'export'])->name('company-ledger.export');
    // Monthly Summary is entered by the office (Sales/HR/Admin, enforced in
    // the controller), not the site team, so it also skips the
    // site_records.manage middleware the other WO entry routes use.
    Route::post('summary', [WorkOrderSummaryController::class, 'store'])->name('summary.store');
    Route::put('summary/{summary}', [WorkOrderSummaryController::class, 'update'])->name('summary.update');
    Route::delete('summary/{summary}', [WorkOrderSummaryController::class, 'destroy'])->name('summary.destroy');
    Route::middleware('permission:work_orders.edit|approval_requests.manage')->group(function () {
        Route::post('approval-requests', [ApprovalRequestController::class, 'store'])->name('approval-requests.store');
        Route::post('approval-requests/{approvalRequest}/respond', [ApprovalRequestController::class, 'respond'])->name('approval-requests.respond');
        Route::get('approval-requests/approved-pdf', [ApprovalRequestController::class, 'approvedPdf'])->name('approval-requests.approved-pdf');
        Route::get('approval-requests/{approvalRequest}/pdf', [ApprovalRequestController::class, 'pdf'])->name('approval-requests.pdf');
    });
    // Editing/removing an existing approval request record stays Admin-only
    // (data-integrity action, not part of the raise/respond entry workflow).
    Route::middleware('permission:work_orders.edit')->group(function () {
        Route::put('approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'update'])->name('approval-requests.update');
        Route::delete('approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'destroy'])->name('approval-requests.destroy');
    });
});

Route::middleware('permission:assigned_work.view')->group(function () {
    Route::get('my-work-orders', [MyWorkOrderController::class, 'index'])->name('my-work-orders.index');
    Route::get('my-work-orders/{workOrder}', [MyWorkOrderController::class, 'show'])->name('my-work-orders.show');
});
// tasks.view is held by every internal staff role (Sales, HR, Finance, QC
// Officer, Executive Team Leader, Worker, ...) - not just the roles with
// assigned_work.view - so any employee-linked staff member can reach their
// own payroll self-service page, not only Workers/Executive Team.
Route::middleware('permission:tasks.view')->group(function () {
    Route::get('my-payroll', [MyPayrollController::class, 'index'])->name('my-payroll.index');
    Route::get('my-payroll/{payroll}/pdf', [MyPayrollController::class, 'pdf'])->name('my-payroll.pdf');
    Route::get('my-attendance', [MyAttendanceController::class, 'index'])->name('my-attendance.index');
});

/*
|--------------------------------------------------------------------------
| Human Resources
|--------------------------------------------------------------------------
*/
Route::middleware('permission:employees.view')->group(function () {
    Route::resource('employees', EmployeeController::class);
    Route::delete('employees/{employee}/remove', [EmployeeController::class, 'remove'])->name('employees.remove');
    Route::delete('employees/{employee}/media/{media}', [EmployeeController::class, 'destroyMedia'])->name('employees.media.destroy');

    Route::resource('candidates', CandidateController::class);
    Route::delete('candidates/{candidate}/media/{media}', [CandidateController::class, 'destroyMedia'])->name('candidates.media.destroy');
    Route::post('candidates/{candidate}/decide', [CandidateController::class, 'decide'])->name('candidates.decide');
    Route::get('candidates/{candidate}/hire', [CandidateController::class, 'hireForm'])->name('candidates.hire-form');
    Route::post('candidates/{candidate}/hire', [CandidateController::class, 'hire'])->name('candidates.hire');
});
Route::middleware('permission:attendance.view')->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('attendance/pdf', [AttendanceController::class, 'pdf'])->name('attendance.pdf');
});
Route::middleware('permission:payroll.view')->group(function () {
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/employees', [PayrollController::class, 'employeeIndex'])->name('payroll.employee-index');
    Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::post('payroll/generate-from-attendance', [PayrollController::class, 'generateFromAttendance'])->name('payroll.generate-from-attendance');
    Route::post('payroll/{payroll}/record-payment', [PayrollController::class, 'recordPayment'])->name('payroll.record-payment');
    Route::delete('payroll/{payroll}/payments/{payment}', [PayrollController::class, 'destroyPayment'])->name('payroll.payments.destroy');
    Route::get('payroll/{payroll}/pdf', [PayrollController::class, 'pdf'])->name('payroll.pdf');
    Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
});
Route::middleware('permission:executive_teams.view')->group(function () {
    Route::resource('executive-teams', ExecutiveTeamController::class);
    Route::post('executive-teams/{executiveTeam}/members', [ExecutiveTeamController::class, 'addMember'])->name('executive-teams.members.store');
    Route::delete('executive-teams/{executiveTeam}/members/{member}', [ExecutiveTeamController::class, 'removeMember'])->name('executive-teams.members.destroy');
});

/*
|--------------------------------------------------------------------------
| Subcontractors (Admin)
|--------------------------------------------------------------------------
*/
Route::middleware('permission:subcontractors.view')->group(function () {
    Route::get('subcontractors', [SubcontractorController::class, 'index'])->name('subcontractors.index');
    Route::get('subcontractors/{subcontractor}', [SubcontractorController::class, 'show'])->name('subcontractors.show');
});
Route::middleware('permission:subcontractors.manage')->group(function () {
    Route::put('subcontractors/{subcontractor}', [SubcontractorController::class, 'update'])->name('subcontractors.update');
    Route::delete('subcontractors/{subcontractor}/media/{media}', [SubcontractorController::class, 'destroyMedia'])->name('subcontractors.media.destroy');
    Route::post('subcontractors/{subcontractor}/verify', [SubcontractorController::class, 'verify'])->name('subcontractors.verify');
    Route::post('subcontractors/{subcontractor}/unverify', [SubcontractorController::class, 'unverify'])->name('subcontractors.unverify');
    Route::post('subcontractors/{subcontractor}/assign-site', [SubcontractorController::class, 'assignSite'])->name('subcontractors.assign-site');
    Route::delete('subcontractors/{subcontractor}/unassign-site/{assignment}', [SubcontractorController::class, 'unassignSite'])->name('subcontractors.unassign-site');
    Route::post('subcontractors/{subcontractor}/assign-work-order', [SubcontractorController::class, 'assignWorkOrder'])->name('subcontractors.assign-work-order');
    Route::delete('subcontractors/{subcontractor}/unassign-work-order/{assignment}', [SubcontractorController::class, 'unassignWorkOrder'])->name('subcontractors.unassign-work-order');
});

/*
|--------------------------------------------------------------------------
| Quality Control
|--------------------------------------------------------------------------
*/
Route::middleware('permission:qc.view')->group(function () {
    Route::resource('qc', QcInspectionController::class)->parameters(['qc' => 'qcInspection']);
});

/*
|--------------------------------------------------------------------------
| Tickets
|--------------------------------------------------------------------------
*/
Route::middleware('permission:tickets.view')->group(function () {
    Route::resource('tickets', TicketController::class)->except('destroy');
    Route::post('tickets/{ticket}/comments', [TicketController::class, 'addComment'])->name('tickets.comments.store');
    Route::post('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('tickets/{ticket}/lock', [TicketController::class, 'lock'])->name('tickets.lock');
    Route::delete('tickets/{ticket}/media/{media}', [TicketController::class, 'destroyMedia'])->name('tickets.media.destroy');
});
Route::middleware('permission:tickets.manage')->group(function () {
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
});

/*
|--------------------------------------------------------------------------
| Management: Finance, Legal, Audit, Company Records
|--------------------------------------------------------------------------
*/
Route::middleware('permission:finance.view')->group(function () {
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('finance/expenses/pdf', [FinanceController::class, 'expensesPdf'])->name('finance.expenses.pdf');
});
Route::middleware('permission:subcontractor_finance.view')->group(function () {
    Route::get('finance/my-payments', [FinanceController::class, 'myPayments'])->name('finance.my-payments');
});
// Writing finance records requires finance.manage (Finance role + Admin), not
// just finance.view - Management/Auditor can see this module but not edit it.
Route::middleware('permission:finance.manage')->group(function () {
    Route::post('finance/invoices', [FinanceController::class, 'storeInvoice'])->name('finance.invoices.store');
    Route::put('finance/invoices/{invoice}/status', [FinanceController::class, 'updateInvoiceStatus'])->name('finance.invoices.status');
    Route::delete('finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->name('finance.invoices.destroy');

    Route::post('finance/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
    Route::put('finance/payments/{payment}', [FinanceController::class, 'updatePayment'])->name('finance.payments.update');
    Route::delete('finance/payments/{payment}', [FinanceController::class, 'destroyPayment'])->name('finance.payments.destroy');

    Route::post('finance/vendor-payments', [FinanceController::class, 'storeVendorPayment'])->name('finance.vendor-payments.store');
    Route::put('finance/vendor-payments/{vendorPayment}', [FinanceController::class, 'updateVendorPayment'])->name('finance.vendor-payments.update');
    Route::delete('finance/vendor-payments/{vendorPayment}', [FinanceController::class, 'destroyVendorPayment'])->name('finance.vendor-payments.destroy');

    Route::post('finance/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
    Route::put('finance/expenses/{expense}', [FinanceController::class, 'updateExpense'])->name('finance.expenses.update');
    Route::delete('finance/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('finance.expenses.destroy');
});

Route::middleware('permission:legal.view')->group(function () {
    Route::get('legal', [LegalController::class, 'index'])->name('legal.index');
});
Route::middleware('permission:legal.manage')->group(function () {
    Route::post('legal', [LegalController::class, 'store'])->name('legal.store');
    Route::get('legal/{legalDocument}/edit', [LegalController::class, 'edit'])->name('legal.edit');
    Route::put('legal/{legalDocument}', [LegalController::class, 'update'])->name('legal.update');
    Route::delete('legal/{legalDocument}', [LegalController::class, 'destroy'])->name('legal.destroy');
    Route::delete('legal/{legalDocument}/media/{media}', [LegalController::class, 'destroyMedia'])->name('legal.media.destroy');
});

// The 'create' route must be registered before the wildcard 'audits/{audit}'
// show route below, or GET /audits/create matches show with "create" as the
// {audit} id and 404s on binding instead of running the create action.
Route::middleware('permission:audit.manage')->group(function () {
    Route::resource('audits', AuditController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::delete('audits/{audit}/media/{media}', [AuditController::class, 'destroyMedia'])->name('audits.media.destroy');
});
Route::middleware('permission:audit.view')->group(function () {
    Route::resource('audits', AuditController::class)->only(['index', 'show']);
});

Route::middleware('permission:company_records.view')->group(function () {
    Route::resource('company-records', CompanyRecordController::class)->except('show');
    Route::delete('company-records/{companyRecord}/media/{media}', [CompanyRecordController::class, 'destroyMedia'])->name('company-records.media.destroy');
});

/*
|--------------------------------------------------------------------------
| Task Management (Common Task + Calendar Task)
|--------------------------------------------------------------------------
*/
Route::middleware('permission:tasks.create')->group(function () {
    Route::get('tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::delete('tasks/{task}/media/{media}', [TaskController::class, 'destroyMedia'])->name('tasks.media.destroy');
});
Route::middleware('permission:tasks.manage')->group(function () {
    Route::get('tasks/pdf', [TaskController::class, 'pdf'])->name('tasks.pdf');
});
Route::middleware('permission:tasks.view')->group(function () {
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/delay', [TaskController::class, 'reportDelay'])->name('tasks.delay');
    Route::post('tasks/{task}/verify', [TaskController::class, 'verify'])->name('tasks.verify');
    Route::post('tasks/{task}/retask', [TaskController::class, 'retask'])->name('tasks.retask');

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::post('leave-requests/{leaveRequest}/review', [LeaveRequestController::class, 'review'])->name('leave-requests.review');
    Route::delete('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leave-requests.destroy');
});

/*
|--------------------------------------------------------------------------
| Communication (Chat, Groups, Discussions)
|--------------------------------------------------------------------------
*/
Route::middleware('permission:chat.access')->group(function () {
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/unread-count', [ChatController::class, 'unreadCount'])->name('chat.unread-count');
    Route::post('chat/direct', [ChatController::class, 'startDirect'])->name('chat.direct');
    Route::post('chat/groups', [ChatController::class, 'storeGroup'])->name('chat.groups.store');

    Route::post('conversations/{conversation}/participants', [ChatController::class, 'addParticipant'])->name('conversations.participants.store');
    Route::delete('conversations/{conversation}/participants/{participant}', [ChatController::class, 'removeParticipant'])->name('conversations.participants.destroy');
    Route::post('conversations/{conversation}/leave', [ChatController::class, 'leaveGroup'])->name('conversations.leave');
    Route::post('conversations/{conversation}/messages', [ChatController::class, 'store'])->name('conversations.messages.store');
    Route::get('conversations/{conversation}/poll', [ChatController::class, 'poll'])->name('conversations.poll');
    Route::delete('conversations/{conversation}/media/{media}', [ChatController::class, 'destroyMedia'])->name('conversations.media.destroy');
});
Route::middleware('permission:tasks.manage')->group(function () {
    Route::resource('admin/task-schedules', TaskScheduleController::class)
        ->except('show')
        ->names('admin.task-schedules')
        ->parameters(['task-schedules' => 'taskSchedule']);
});

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/
Route::middleware('permission:reports.view')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/work-orders/export', [ReportController::class, 'exportWorkOrders'])->name('reports.work-orders.export');
    Route::get('reports/payroll/export', [ReportController::class, 'exportPayroll'])->name('reports.payroll.export');
    Route::get('reports/tickets/export', [ReportController::class, 'exportTickets'])->name('reports.tickets.export');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::middleware('permission:users.view')->group(function () {
    Route::resource('admin/users', UserController::class)->except('show')->names('admin.users');
    Route::post('admin/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('admin.users.deactivate');
});
Route::middleware('permission:roles.view')->group(function () {
    Route::resource('admin/roles', RoleController::class)->except('show')->names('admin.roles');
});
Route::middleware('permission:masters.manage')->group(function () {
    Route::resource('admin/departments', DepartmentController::class)->except(['create', 'edit', 'show'])->names('admin.departments');
});
Route::middleware('permission:activity_logs.view')->group(function () {
    Route::get('admin/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
});
Route::middleware('permission:system_settings.manage')->group(function () {
    Route::get('admin/settings/mail', [SettingsController::class, 'editMail'])->name('admin.settings.mail.edit');
    Route::put('admin/settings/mail', [SettingsController::class, 'updateMail'])->name('admin.settings.mail.update');
    Route::post('admin/settings/mail/test', [SettingsController::class, 'sendTest'])->name('admin.settings.mail.test');
});

/*
|--------------------------------------------------------------------------
| Client Portal
|--------------------------------------------------------------------------
*/
Route::middleware('permission:client_portal.access')->prefix('portal')->name('portal.')->group(function () {
    Route::get('quotations', [PortalQuotationController::class, 'index'])->name('quotations.index');
    Route::get('quotations/{quotation}', [PortalQuotationController::class, 'show'])->name('quotations.show');
    Route::post('quotations/{quotation}/approve', [PortalQuotationController::class, 'approve'])->name('quotations.approve');
    Route::post('quotations/{quotation}/reject', [PortalQuotationController::class, 'reject'])->name('quotations.reject');
    Route::get('work-orders', [PortalWorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('work-orders/{workOrder}', [PortalWorkOrderController::class, 'show'])->name('work-orders.show');
    Route::post('work-orders/{workOrder}/accept', [PortalWorkOrderController::class, 'accept'])->name('work-orders.accept');
    Route::post('work-orders/{workOrder}/approval-requests', [PortalApprovalRequestController::class, 'store'])->name('work-orders.approval-requests.store');
    Route::post('work-orders/{workOrder}/approval-requests/{approvalRequest}/respond', [PortalApprovalRequestController::class, 'respond'])->name('work-orders.approval-requests.respond');
    Route::get('work-orders/{workOrder}/approval-requests/approved-pdf', [PortalApprovalRequestController::class, 'approvedPdf'])->name('work-orders.approval-requests.approved-pdf');
    Route::get('work-orders/{workOrder}/approval-requests/{approvalRequest}/pdf', [PortalApprovalRequestController::class, 'pdf'])->name('work-orders.approval-requests.pdf');
    Route::get('tickets', [PortalTicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [PortalTicketController::class, 'store'])->name('tickets.store');
    Route::get('invoices', [PortalInvoiceController::class, 'index'])->name('invoices.index');
});
