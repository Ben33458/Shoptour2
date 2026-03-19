@extends('admin.layout')

@section('title', 'LMIV verwalten')

@section('content')
<div class="card">
    <div class="card-header">
        Basis-Artikel mit LMIV-Status ({{ $products->total() }})
        <span style="font-size:.8em;color:var(--c-muted);margin-left:8px">— nur Basis-Artikel werden angezeigt</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artikelnummer</th>
                    <th>Produktname</th>
                    <th style="text-align:center">LMIV-Version</th>
                    <th style="text-align:center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td><code>{{ $product->artikelnummer }}</code></td>
                    <td>{{ $product->produktname }}</td>
                    <td style="text-align:center">
                        @if($product->activeLmivVersion)
                            <span class="badge badge-delivered">v{{ $product->activeLmivVersion->version_number }}</span>
                        @else
                            <span class="badge badge-warning">Leer</span>
                        @endif
                    </td>
                    <td style="text-align:center">
                        @if($product->activeLmivVersion)
                            <span class="badge badge-delivered">Aktiv</span>
                        @else
                            <span class="badge" style="background:var(--c-warning-bg,#fef9c3);color:#854d0e">Fehlt</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <a href="{{ route('admin.lmiv.edit', $product) }}"
                           class="btn btn-primary btn-sm">LMIV bearbeiten</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Keine Basis-Artikel gefunden.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $products->links() }}
@endsection
