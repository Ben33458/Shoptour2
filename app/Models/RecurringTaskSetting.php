<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringTaskSetting extends Model
{
    protected $primaryKey = 'ninox_task_id';
    public $incrementing  = false;

    protected $fillable = ['ninox_task_id', 'recurrence_basis'];
}
