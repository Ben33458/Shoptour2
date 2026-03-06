@extends('admin.layout')

@section('title', 'Neuer Kunde')

@section('actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline btn-sm">← Kundenliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Kundendaten eingeben</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.customers.store') }}">
            @csrf
            @include('admin.customers._form', ['customer' => null])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Kunde anlegen</button>
                <a href="{{ route('admin.customers.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@endsection
