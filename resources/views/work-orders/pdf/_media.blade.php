{{-- Lists an attachment's file name against the section/entry it belongs to
     - shared by every PDF export (WO sections, approval requests). The file
     itself isn't embedded (images included) to keep the PDF small; the
     actual file is opened from the ZIP download instead. --}}
@if ($media)
    <p class="muted" style="margin-top:2px;">{{ $label ?? 'Attachment' }} — <a href="{{ $media->getFullUrl() }}">{{ $media->file_name }}</a></p>
@endif
