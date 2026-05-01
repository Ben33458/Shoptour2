@extends('shop.layout')

@section('title', $page->title)

@push('head')
<style>
.cms-content h1 { font-size: 1.6rem; font-weight: 700; color: #1e293b; margin: 2rem 0 .75rem; }
.cms-content h2 { font-size: 1.2rem; font-weight: 700; color: #1e293b; margin: 2rem 0 .5rem; padding-bottom: .3rem; border-bottom: 2px solid #1e90d0; }
.cms-content h3 { font-size: 1rem; font-weight: 600; color: #334155; margin: 1.25rem 0 .4rem; }
.cms-content p  { color: #475569; line-height: 1.75; margin: .5rem 0 1rem; }
.cms-content ul { list-style: disc; padding-left: 1.5rem; margin: .5rem 0 1rem; }
.cms-content ul li { color: #475569; line-height: 1.7; margin-bottom: .25rem; }
.cms-content strong { color: #1e293b; }
.cms-content a { color: #1e90d0; text-decoration: underline; }
.cms-content .section-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.25rem 1.5rem;
    margin: 1.25rem 0;
}
</style>
@endpush


@section('content')
<div class="max-w-3xl mx-auto py-4">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $page->title }}</h1>
    <p class="text-gray-400 text-sm mb-8">Zuletzt aktualisiert: {{ $page->updated_at->format('d.m.Y') }}</p>
    <div class="cms-content">
        {!! $page->content !!}
    </div>
</div>
@endsection
