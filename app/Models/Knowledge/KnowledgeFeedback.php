<?php

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeFeedback extends Model
{
    protected $table = 'knowledge_feedback';

    protected $fillable = ['query_id', 'user_id', 'reaction', 'comment'];

    public const REACTION_HELPFUL    = 'helpful';
    public const REACTION_WRONG      = 'wrong';
    public const REACTION_INCOMPLETE = 'incomplete';
    public const REACTION_OUTDATED   = 'outdated';
    public const REACTION_CRITICAL   = 'critical';
    public const REACTION_GOOD_IDEA  = 'good_idea';
    public const REACTION_ADOPT_RULE = 'adopt_rule';
    public const REACTION_LIFESAVER  = 'lifesaver';
    public const REACTION_SUSPICIOUS = 'suspicious';
    public const REACTION_REMOVE     = 'remove';

    public const REACTIONS = [
        self::REACTION_HELPFUL    => '👍 hilfreich',
        self::REACTION_WRONG      => '👎 falsch',
        self::REACTION_INCOMPLETE => '🧩 unvollständig',
        self::REACTION_OUTDATED   => '🕒 veraltet',
        self::REACTION_CRITICAL   => '⚠️ kritisch',
        self::REACTION_GOOD_IDEA  => '💡 gute Idee',
        self::REACTION_ADOPT_RULE => '📌 als Regel übernehmen',
        self::REACTION_LIFESAVER  => '🧯 rettet gerade den Laden',
        self::REACTION_SUSPICIOUS => '🤨 klingt fragwürdig',
        self::REACTION_REMOVE     => '🗑️ kann weg',
    ];

    public function knowledgeQuery(): BelongsTo
    {
        return $this->belongsTo(KnowledgeQuery::class, 'query_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
