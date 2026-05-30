@extends('admin.layout')

@section('title', $title)

@section('actions')
    <a href="{{ $back_route }}" class="btn btn-outline btn-sm">← Zurück</a>
@endsection

@section('content')

<div style="max-width:860px;margin:0 auto">

    {{-- Meta-Info & Senden-Button --}}
    <div class="card" style="margin-bottom:16px;padding:16px 20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap">
            <div style="display:grid;grid-template-columns:auto 1fr;gap:6px 16px;font-size:.9rem;align-items:baseline">
                <span class="hint" style="white-space:nowrap">Empfänger</span>
                <strong>{{ $recipient }}</strong>
                <span class="hint" style="white-space:nowrap">Betreff</span>
                <span>{{ $subject }}</span>
            </div>
            <form method="POST" action="{{ $send_route }}" style="flex-shrink:0">
                @csrf
                @foreach($send_params as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm('E-Mail jetzt wirklich versenden?')">
                    {{ $send_label }}
                </button>
            </form>
        </div>
    </div>

    {{-- Email HTML Preview --}}
    <div class="card" style="padding:0;overflow:hidden">
        <div class="card-header" style="border-bottom:1px solid var(--c-border)">E-Mail-Vorschau</div>
        <iframe srcdoc="{{ $html }}"
                style="width:100%;min-height:600px;border:none;display:block"
                title="E-Mail-Vorschau"></iframe>
    </div>

    <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center">
        <a href="{{ $back_route }}" class="btn btn-outline btn-sm">← Zurück ohne Senden</a>
        <form method="POST" action="{{ $send_route }}">
            @csrf
            @foreach($send_params as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('E-Mail jetzt wirklich versenden?')">
                {{ $send_label }}
            </button>
        </form>
    </div>

</div>

@endsection
