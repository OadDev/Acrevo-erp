<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">{{ $workOrder->work_order_no }} — {{ $workOrder->title }}</p>
    <p class="muted">{{ $workOrder->site?->site_no ? $workOrder->site->site_no.' &middot; ' : '' }}{{ $workOrder->client?->name }} &middot; Generated {{ now()->format('d M Y, h:i A') }}</p>

    @include('work-orders.pdf._body', ['workOrder' => $workOrder, 'sections' => $sections])
</body>
</html>
