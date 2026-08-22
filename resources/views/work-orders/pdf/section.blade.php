<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">{{ $workOrder->work_order_no }} — {{ $workOrder->title }}</p>
    <p class="muted">{{ \App\Support\WorkOrderPdfSections::SECTIONS[$section] }} &middot; Generated {{ now()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>

    @include('work-orders.pdf._body', ['workOrder' => $workOrder, 'sections' => [$section]])
</body>
</html>
