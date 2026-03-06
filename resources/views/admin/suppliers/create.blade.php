@extends('admin.layout')

@section('title', 'Neuer Lieferant')

@section('actions')
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline btn-sm">← Lieferantenliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Lieferant anlegen</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.suppliers.store') }}">
            @csrf
            @include('admin.suppliers._form', ['supplier' => null])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Lieferant anlegen</button>
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@endsection
