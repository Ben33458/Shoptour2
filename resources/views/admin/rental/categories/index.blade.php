@extends('admin.layout')

@section('title', 'Leihgeräte-Kategorien')

@section('actions')
    <a href="{{ route('admin.rental.categories.create') }}" class="btn btn-primary btn-sm">+ Neue Kategorie</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        Leihgeräte-Kategorien ({{ count($categories) }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th style="text-align:center">Sortierung</th>
                    <th style="text-align:center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td><code>{{ $category->slug }}</code></td>
                    <td style="text-align:center">{{ $category->sort_order }}</td>
                    <td style="text-align:center">
                        @if($category->active)
                            <span class="badge badge-delivered">aktiv</span>
                        @else
                            <span class="badge badge-cancelled">inaktiv</span>
                        @endif
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="{{ route('admin.rental.categories.edit', $category) }}"
                           class="btn btn-outline btn-sm">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.rental.categories.destroy', $category) }}"
                              style="display:inline"
                              onsubmit="return confirm('Kategorie \"{{ addslashes($category->name) }}\" wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm"
                                    style="color:var(--c-danger)">Löschen</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">
                        Noch keine Kategorien angelegt.
                        <a href="{{ route('admin.rental.categories.create') }}"
                           class="btn btn-primary btn-sm" style="margin-left:12px">
                            + Erste Kategorie anlegen
                        </a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
