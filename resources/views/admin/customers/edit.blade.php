@extends('admin.layout')

@section('title', 'Kunde bearbeiten — ' . ($customer->first_name . ' ' . $customer->last_name ?: $customer->customer_number))

@section('actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline btn-sm">← Kundenliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Kundendaten bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
            @csrf
            @method('PUT')
            @include('admin.customers._form', ['customer' => $customer])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                <a href="{{ route('admin.customers.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@include('admin.customers._addresses_section', ['customer' => $customer])

@endsection
