@php
    $isClient = auth()->user()->hasRole('Client');
@endphp
<aside
    x-data="{ open: false }"
    x-cloak
    @toggle-sidebar.window="open = !open"
    :class="open ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 w-64 transform bg-white transition-transform duration-200 ease-in-out dark:bg-gray-900 dark:border-gray-800 border-r border-gray-200 lg:static lg:translate-x-0 flex flex-col"
>
    <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-5 dark:border-gray-800">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">GW</div>
        <span class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white">{{ config('app.name', 'Geethan Works ERP') }}</span>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        <x-nav-group label="Overview">
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Dashboard</x-nav-link>
        </x-nav-group>

        @can('chat.access')
            @php($chatUnread = auth()->user()->unreadConversationCount())
            <x-nav-group label="Communication">
                <x-nav-link :href="route('chat.index', ['scope' => 'direct'])" :active="request()->routeIs('chat.*') && request('scope') !== 'group'" icon="message-circle" id="nav-chats-link" :badge="$chatUnread > 0 ? $chatUnread : null">Chats</x-nav-link>
                <x-nav-link :href="route('chat.index', ['scope' => 'group'])" :active="request()->routeIs('chat.*') && request('scope') === 'group'" icon="users-round">Groups</x-nav-link>
            </x-nav-group>
        @endcan

        @if ($isClient)
            <x-nav-group label="My Projects">
                <x-nav-link :href="route('portal.quotations.index')" :active="request()->routeIs('portal.quotations.*')" icon="file-text">Quotations</x-nav-link>
                <x-nav-link :href="route('portal.work-orders.index')" :active="request()->routeIs('portal.work-orders.*')" icon="briefcase">Current Projects</x-nav-link>
                <x-nav-link :href="route('portal.tickets.index')" :active="request()->routeIs('portal.tickets.*')" icon="ticket">Tickets</x-nav-link>
                <x-nav-link :href="route('portal.invoices.index')" :active="request()->routeIs('portal.invoices.*')" icon="receipt">Invoices &amp; Payments</x-nav-link>
            </x-nav-group>
        @else
            @canany(['enquiries.view', 'site_visits.view', 'quotations.view', 'work_orders.view'])
                <x-nav-group label="Sales & Marketing">
                    @can('enquiries.view')
                        <x-nav-link :href="route('enquiries.index')" :active="request()->routeIs('enquiries.*')" icon="inbox">Enquiries</x-nav-link>
                        <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')" icon="users">Clients</x-nav-link>
                    @endcan
                    @can('site_visits.view')
                        <x-nav-link :href="route('site-visits.index')" :active="request()->routeIs('site-visits.*')" icon="map-pin">Site Visits</x-nav-link>
                    @endcan
                    @can('quotations.view')
                        <x-nav-link :href="route('quotations.index')" :active="request()->routeIs('quotations.*')" icon="file-text">Quotations</x-nav-link>
                    @endcan
                    @can('work_orders.view')
                        <x-nav-link :href="route('sites.index')" :active="request()->routeIs('sites.*')" icon="map-pin">Sites</x-nav-link>
                        <x-nav-link :href="route('work-orders.index')" :active="request()->routeIs('work-orders.index') || request()->routeIs('work-orders.show') || request()->routeIs('work-orders.create')" icon="clipboard">Work Orders</x-nav-link>
                        <x-nav-link :href="route('work-orders.completed')" :active="request()->routeIs('work-orders.completed')" icon="check-circle">Completed Sites</x-nav-link>
                    @endcan
                </x-nav-group>
            @endcanany

            @if (auth()->user()->can('assigned_work.view') || (auth()->user()->can('tasks.view') && auth()->user()->employee) || auth()->user()->can('subcontractor_finance.view'))
                <x-nav-group label="Executive Team">
                    @can('assigned_work.view')
                        <x-nav-link :href="route('my-work-orders.index')" :active="request()->routeIs('my-work-orders.*')" icon="hard-hat">My Work Orders</x-nav-link>
                    @endcan
                    @can('tasks.view')
                        @if (auth()->user()->employee)
                            <x-nav-link :href="route('my-payroll.index')" :active="request()->routeIs('my-payroll.*')" icon="wallet">My Payroll</x-nav-link>
                            <x-nav-link :href="route('my-attendance.index')" :active="request()->routeIs('my-attendance.*')" icon="calendar-check">My Attendance</x-nav-link>
                        @endif
                    @endcan
                    @can('subcontractor_finance.view')
                        <x-nav-link :href="route('finance.my-payments')" :active="request()->routeIs('finance.my-payments')" icon="banknote">Finance</x-nav-link>
                    @endcan
                </x-nav-group>
            @endif

            @can('subcontractors.view')
                <x-nav-group label="Subcontractors">
                    <x-nav-link :href="route('subcontractors.index')" :active="request()->routeIs('subcontractors.*')" icon="hard-hat">Subcontractors</x-nav-link>
                </x-nav-group>
            @endcan

            @canany(['employees.view', 'attendance.view', 'payroll.view', 'executive_teams.view'])
                <x-nav-group label="Human Resources">
                    @can('employees.view')
                        <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="users">Workers</x-nav-link>
                        <x-nav-link :href="route('candidates.index')" :active="request()->routeIs('candidates.*')" icon="search">Candidates / Interviews</x-nav-link>
                    @endcan
                    @can('attendance.view')
                        <x-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.*')" icon="calendar-check">Attendance</x-nav-link>
                    @endcan
                    @can('payroll.view')
                        <x-nav-link :href="route('payroll.index')" :active="request()->routeIs('payroll.index')" icon="wallet">WO Workers Payroll</x-nav-link>
                        <x-nav-link :href="route('payroll.employee-index')" :active="request()->routeIs('payroll.employee-index')" icon="wallet">Employee Payroll</x-nav-link>
                    @endcan
                    @can('executive_teams.view')
                        <x-nav-link :href="route('executive-teams.index')" :active="request()->routeIs('executive-teams.*')" icon="users-round">Executive Teams</x-nav-link>
                    @endcan
                </x-nav-group>
            @endcanany

            @can('qc.view')
                <x-nav-group label="Quality Control">
                    <x-nav-link :href="route('qc.index')" :active="request()->routeIs('qc.*')" icon="shield-check">QC Inspections</x-nav-link>
                </x-nav-group>
            @endcan

            @can('tickets.view')
                <x-nav-group label="Support">
                    <x-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')" icon="ticket">Tickets</x-nav-link>
                </x-nav-group>
            @endcan

            @can('tasks.view')
                <x-nav-group label="Task">
                    <x-nav-link :href="route('tasks.index', ['type' => 'common'])" :active="request()->routeIs('tasks.*') && request('type') !== 'calendar'" icon="clipboard">Common Task</x-nav-link>
                    <x-nav-link :href="route('tasks.index', ['type' => 'calendar'])" :active="request()->routeIs('tasks.*') && request('type') === 'calendar'" icon="calendar-check">Calendar Task</x-nav-link>
                    <x-nav-link :href="route('leave-requests.index')" :active="request()->routeIs('leave-requests.*')" icon="calendar-check">Leave Requests</x-nav-link>
                </x-nav-group>
            @endcan

            @canany(['finance.view', 'legal.view', 'audit.view', 'company_records.view'])
                <x-nav-group label="Management">
                    @can('finance.view')
                        <x-nav-link :href="route('finance.index')" :active="request()->routeIs('finance.*')" icon="banknote">Finance</x-nav-link>
                    @endcan
                    @can('legal.view')
                        <x-nav-link :href="route('legal.index')" :active="request()->routeIs('legal.*')" icon="scale">Legal</x-nav-link>
                    @endcan
                    @can('audit.view')
                        <x-nav-link :href="route('audits.index')" :active="request()->routeIs('audits.*')" icon="search-check">Auditing</x-nav-link>
                    @endcan
                    @can('company_records.view')
                        <x-nav-link :href="route('company-records.index')" :active="request()->routeIs('company-records.*')" icon="building">Company Records</x-nav-link>
                    @endcan
                </x-nav-group>
            @endcanany

            @can('assets.view')
                <x-nav-group label="Equipment &amp; Assets">
                    <x-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*') && ! request()->routeIs('asset-change-requests.*')" icon="wrench">Assets</x-nav-link>
                    @can('movements.view')
                        <x-nav-link :href="route('asset-movements.index')" :active="request()->routeIs('asset-movements.*')" icon="history">Movement History</x-nav-link>
                    @endcan
                    @can('repairs.view')
                        <x-nav-link :href="route('asset-repairs.index')" :active="request()->routeIs('asset-repairs.*')" icon="wrench">Repair History</x-nav-link>
                    @endcan
                    @can('verifications.view')
                        <x-nav-link :href="route('asset-verifications.index')" :active="request()->routeIs('asset-verifications.*')" icon="clipboard-check">Verification History</x-nav-link>
                    @endcan
                    @can('assets.view_missing')
                        <x-nav-link :href="route('assets.missing')" :active="request()->routeIs('assets.missing')" icon="search">Missing Equipment</x-nav-link>
                    @endcan
                    @can('equipment_requests.view')
                        <x-nav-link :href="route('equipment-requests.index')" :active="request()->routeIs('equipment-requests.*')" icon="inbox">Equipment Requests</x-nav-link>
                    @endcan
                    @can('assets.approve')
                        <x-nav-link :href="route('asset-change-requests.index')" :active="request()->routeIs('asset-change-requests.*')" icon="clipboard-check">Pending Approvals</x-nav-link>
                    @endcan
                </x-nav-group>
            @endcan

            @can('reports.view')
                <x-nav-group label="Insights">
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="bar-chart">Reports</x-nav-link>
                </x-nav-group>
            @endcan

            @canany(['users.view', 'roles.view', 'activity_logs.view', 'tasks.manage', 'masters.manage', 'system_settings.manage'])
                <x-nav-group label="Administration">
                    @can('users.view')
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="user-cog">Users</x-nav-link>
                    @endcan
                    @can('roles.view')
                        <x-nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')" icon="key">Roles &amp; Permissions</x-nav-link>
                    @endcan
                    @can('masters.manage')
                        <x-nav-link :href="route('admin.departments.index')" :active="request()->routeIs('admin.departments.*')" icon="building">Departments</x-nav-link>
                    @endcan
                    @can('tasks.manage')
                        <x-nav-link :href="route('admin.task-schedules.index')" :active="request()->routeIs('admin.task-schedules.*')" icon="calendar-check">Calendar Task Management</x-nav-link>
                    @endcan
                    @can('activity_logs.view')
                        <x-nav-link :href="route('admin.activity-logs.index')" :active="request()->routeIs('admin.activity-logs.*')" icon="history">Activity Logs</x-nav-link>
                    @endcan
                    @can('system_settings.manage')
                        <x-nav-link :href="route('admin.settings.mail.edit')" :active="request()->routeIs('admin.settings.*')" icon="mail">Mail Settings</x-nav-link>
                    @endcan
                </x-nav-group>
            @endcanany
        @endif
    </nav>
</aside>

<div
    x-data
    @toggle-sidebar.window="$el.classList.toggle('hidden')"
    class="fixed inset-0 z-30 hidden bg-gray-900/50 lg:hidden"
    @click="$dispatch('toggle-sidebar')"
></div>
