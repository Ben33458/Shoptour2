@extends('admin.layout')

@section('title', 'Seiten')

@section('content')
<div class="card">
    <div class="card-header">
        <strong>Rechtliche Seiten</strong>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Slug (URL)</th>
                    <th>Zuletzt geändert</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($pages as $page)
                    <tr>
                        <td><strong>{{ $page->title }}</strong></td>
                        <td>
                            <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="text-muted">
                                /seite/{{ $page->slug }}
                            </a>
                        </td>
                        <td>{{ $page->updated_at->format('d.m.Y H:i') }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-outline">
                                Bearbeiten
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
