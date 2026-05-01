@extends('mein.layout')

@section('title', 'Neuigkeiten')

@section('content')

<div style="font-size:20px;font-weight:700;color:var(--c-text);margin-bottom:20px;">
    📢 Neuigkeiten
    <span style="font-size:13px;font-weight:400;color:var(--c-muted);margin-left:8px;">Liveblog vom Team</span>
</div>

@if($posts->isEmpty())
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:40px;text-align:center;color:var(--c-muted);">
    Noch keine Beiträge veröffentlicht.
</div>
@else

@foreach($posts as $post)
<div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:10px;padding:20px;margin-bottom:14px;box-shadow:var(--shadow);">
    <div style="display:flex;align-items:flex-start;gap:14px;">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--c-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0;">
            📢
        </div>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--c-text);">{{ $post->author_name }}</div>
                    @if($post->task)
                    <div style="font-size:11px;color:var(--c-muted);margin-top:2px;">
                        Aufgabe: {{ $post->task->title }}
                    </div>
                    @endif
                </div>
                <div style="font-size:11px;color:var(--c-muted);">{{ $post->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <div style="white-space:pre-wrap;line-height:1.6;font-size:14px;color:var(--c-text);">{{ $post->body }}</div>
            @if($post->images && count($post->images) > 0)
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                @foreach($post->images as $img)
                    <a href="{{ asset('storage/' . $img) }}" target="_blank">
                        <img src="{{ asset('storage/' . $img) }}" style="width:120px;height:90px;object-fit:cover;border-radius:6px;border:1px solid var(--c-border);">
                    </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endforeach

{{ $posts->links() }}

@endif
@endsection
