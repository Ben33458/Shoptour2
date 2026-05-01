@extends('shop.layout')

@section('title', $title ?? 'Mein Konto')

@section('content')
<div class="flex gap-8 items-start">

    {{-- Sidebar --}}
    <aside class="w-52 shrink-0 hidden md:block">
        @include('components.account-sidebar')
    </aside>

    {{-- Main content --}}
    <div class="flex-1 min-w-0">
        @yield('account-content')
    </div>

</div>
@endsection
