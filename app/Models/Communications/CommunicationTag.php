<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommunicationTag extends Model
{
    protected $table = 'communication_tags';

    protected $fillable = ['company_id', 'name', 'color'];

    public function communications(): BelongsToMany
    {
        return $this->belongsToMany(Communication::class, 'communication_tag_pivot', 'tag_id', 'communication_id');
    }
}
