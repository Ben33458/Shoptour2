<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTablePreferencesController extends Controller
{
    public function show(string $key): JsonResponse
    {
        $row = DB::table('user_table_preferences')
            ->where('user_id', auth()->id())
            ->where('table_key', $key)
            ->value('preferences');

        return response()->json($row ? json_decode($row, true) : (object) []);
    }

    public function store(Request $request, string $key): JsonResponse
    {
        $request->validate(['preferences' => ['required', 'array']]);

        DB::table('user_table_preferences')->upsert(
            [
                'user_id'     => auth()->id(),
                'table_key'   => $key,
                'preferences' => json_encode($request->input('preferences')),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            ['user_id', 'table_key'],
            ['preferences', 'updated_at'],
        );

        return response()->json(['ok' => true]);
    }
}
