{{-- Embeds an uploaded image inline, links a document, and silently skips
     videos - shared by every PDF export (WO sections, approval requests). --}}
@if ($media)
    @php
        $isImage = str_starts_with($media->mime_type, 'image');
        $isVideo = str_starts_with($media->mime_type, 'video');
        $encoded = null;
        if ($isImage) {
            try {
                $encoded = base64_encode(file_get_contents($media->getPath()));
            } catch (\Throwable $e) {
                $encoded = null;
            }
        }
    @endphp
    @if ($isImage && $encoded)
        <div style="margin-top:6px;">
            <p class="muted" style="margin:0 0 2px;">{{ $label ?? 'Attachment' }}: {{ $media->file_name }}</p>
            <img src="data:{{ $media->mime_type }};base64,{{ $encoded }}" style="max-width: 260px; max-height: 200px; border: 1px solid #e5e7eb; border-radius: 4px;">
        </div>
    @elseif (! $isVideo)
        <p class="muted" style="margin-top:4px;">{{ $label ?? 'Attachment' }}: <a href="{{ $media->getFullUrl() }}">{{ $media->file_name }}</a></p>
    @endif
@endif
