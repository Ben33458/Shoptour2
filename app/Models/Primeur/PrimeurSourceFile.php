<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;

class PrimeurSourceFile extends Model
{
    protected $table = 'primeur_source_files';

    protected $fillable = [
        'import_run_id', 'file_path', 'file_name', 'file_size',
        'source_type', 'data_date', 'records_imported',
    ];

    protected $casts = [
        'data_date' => 'date',
    ];
}
