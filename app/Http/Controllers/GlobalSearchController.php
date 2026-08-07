<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->get('q'));
        $user = $request->user();
        $results = collect();

        if ($query !== '') {
            if ($user->can('enquiries.view')) {
                $results = $results->merge(
                    Enquiry::query()
                        ->where(fn ($q) => $q->where('contact_name', 'like', "%{$query}%")
                            ->orWhere('enquiry_no', 'like', "%{$query}%")
                            ->orWhere('contact_phone', 'like', "%{$query}%"))
                        ->limit(5)->get()
                        ->map(fn ($e) => ['type' => 'Enquiry', 'title' => $e->enquiry_no.' — '.$e->contact_name, 'url' => route('enquiries.show', $e)])
                );
            }

            if ($user->can('work_orders.view') || $user->can('assigned_work.view')) {
                $results = $results->merge(
                    WorkOrder::query()
                        ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")
                            ->orWhere('work_order_no', 'like', "%{$query}%"))
                        ->limit(5)->get()
                        ->map(fn ($w) => ['type' => 'Work Order', 'title' => $w->work_order_no.' — '.$w->title, 'url' => route('work-orders.show', $w)])
                );
            }

            if ($user->can('tickets.view')) {
                $results = $results->merge(
                    Ticket::query()
                        ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")
                            ->orWhere('ticket_no', 'like', "%{$query}%"))
                        ->limit(5)->get()
                        ->map(fn ($t) => ['type' => 'Ticket', 'title' => $t->ticket_no.' — '.$t->title, 'url' => route('tickets.show', $t)])
                );
            }

            if ($user->can('employees.view')) {
                $results = $results->merge(
                    Employee::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")
                            ->orWhere('employee_code', 'like', "%{$query}%"))
                        ->limit(5)->get()
                        ->map(fn ($e) => ['type' => 'Employee', 'title' => $e->employee_code.' — '.$e->name, 'url' => route('employees.show', $e)])
                );
            }

            if ($user->can('enquiries.view') || $user->can('work_orders.view')) {
                $results = $results->merge(
                    Client::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%"))
                        ->limit(5)->get()
                        ->map(fn ($c) => ['type' => 'Client', 'title' => $c->client_code.' — '.$c->name, 'url' => route('clients.show', $c)])
                );
            }
        }

        return view('search.index', ['query' => $query, 'results' => $results]);
    }
}
