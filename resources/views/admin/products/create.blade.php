@extends('admin.layout')

@section('title', 'Neues Produkt anlegen')

@section('actions')
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline btn-sm">← Produktliste</a>
@endsection

@section('content')

<div class="card">
    <div class="card-header">Produktdaten eingeben</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.products.store') }}">
            @csrf
            @include('admin.products._form', ['product' => null])
            <div style="margin-top:20px">
                <button type="submit" class="btn btn-primary">Produkt anlegen</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline" style="margin-left:8px">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

@endsection
