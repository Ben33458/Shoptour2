@extends('admin.layout')

@section('title', 'Lager bearbeiten: ' . $warehouse->name)

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline btn-sm">← Zurück</a>
    <a href="{{ route('admin.warehouses.show', $warehouse) }}" class="btn btn-outline btn-sm">Bestände ansehen</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.warehouses.update', $warehouse) }}">
    @csrf
    @method('PUT')

    @include('admin.warehouses._form', ['warehouse' => $warehouse])

    <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline">Abbrechen</a>
    </div>
</form>
@endsection
