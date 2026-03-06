@extends('admin.layout')

@section('title', 'Lieferant bearbeiten — ' . $supplier->name)

@section('actions')
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline btn-sm">← Lieferantenliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Lieferantendaten bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            @include('admin.suppliers._form', ['supplier' => $supplier])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@endsection
