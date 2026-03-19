@extends('admin.layout')

@section('title', 'CMS-Seiten')

@section('actions')
    <a href="{{ route('admin.pages.create') }}" class="btn btn-primary btn-sm">+ Neue Seite</a>
@endsection

@section('content')

@php
    $menuLabels = [
        'main'   => '🏠 Hauptmenü',
        'footer' => '📄 Footer',
        'none'   => '🔗 Kein Menü',
    ];
    $menuOrder = ['main', 'footer', 'none'];
@endphp

@foreach($menuOrder as $menuKey)
    @php $group = $pages->get($menuKey, collect()); @endphp
    @if($group->isNotEmpty())
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            {{ $menuLabels[$menuKey] ?? $menuKey }}
            <span class="badge" style="margin-left:6px">{{ $group->count() }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>URL</th>
                        <th style="text-align:center">Reihenfolge</th>
                        <th style="text-align:center">Status</th>
                        <th>Geändert</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($group as $page)
                    <tr>
                        <td><strong>{{ $page->title }}</strong></td>
                        <td>
                            <a href="{{ route('page.show', $page->slug) }}" target="_blank"
                               class="text-muted" style="font-size:.85em">
                                /seite/{{ $page->slug }}
                            </a>
                        </td>
                        <td style="text-align:center;color:var(--c-muted);font-size:.85em">
                            {{ $page->sort_order }}
                        </td>
                        <td style="text-align:center">
                            @if($page->active)
                                <span class="badge badge-delivered">aktiv</span>
                            @else
                                <span class="badge badge-cancelled">inaktiv</span>
                            @endif
                        </td>
                        <td style="font-size:.85em;color:var(--c-muted)">
                            {{ $page->updated_at->format('d.m.Y H:i') }}
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <a href="{{ route('admin.pages.edit', $page) }}"
                               class="btn btn-sm btn-outline">Bearbeiten</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}"
                                  style="display:inline"
                                  onsubmit="return confirm('Seite \"{{ addslashes($page->title) }}\" wirklich löschen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline"
                                        style="color:var(--c-danger)">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endforeach

@if($pages->isEmpty())
    <div class="card" style="padding:32px;text-align:center;color:var(--c-muted)">
        Noch keine Seiten angelegt.
    </div>
@endif

@endsection
