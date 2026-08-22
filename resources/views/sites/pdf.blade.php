<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Site {{ $site->site_no }} — {{ $site->client?->name }}</p>
    <p class="muted">{{ collect([$site->address, $site->city, $site->state, $site->pincode])->filter()->join(', ') ?: '—' }}</p>
    <p class="muted">{{ $workOrders->count() }} work order(s) &middot; Generated {{ now()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>

    @forelse ($workOrders as $workOrder)
        <h1 class="wo-heading">{{ $workOrder->work_order_no }} — {{ $workOrder->title }}</h1>
        <p class="muted">{{ $workOrder->client?->name }} &middot; Status: {{ Str::title(str_replace('_', ' ', $workOrder->status)) }}</p>

        @include('work-orders.pdf._body', ['workOrder' => $workOrder, 'sections' => $sections])
    @empty
        <p class="empty">No work orders at this site yet.</p>
    @endforelse
</body>
</html>
