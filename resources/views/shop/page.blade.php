@extends('shop.layout')

@section('title', $page->title)

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $page->title }}</h1>
    <div class="prose prose-gray max-w-none text-gray-700 leading-relaxed">
        {!! $page->content !!}
    </div>
</div>
@endsection
