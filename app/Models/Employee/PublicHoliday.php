<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = ['date', 'name', 'state', 'is_half_day'];
    protected $casts = ['date' => 'date', 'is_half_day' => 'boolean'];
}
