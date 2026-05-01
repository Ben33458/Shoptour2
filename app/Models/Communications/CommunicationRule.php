<?php

declare(strict_types=1);

namespace App\Models\Communications;

use Illuminate\Database\Eloquent\Model;

class CommunicationRule extends Model
{
    // Condition types
    public const COND_FROM_DOMAIN       = 'from_domain';
    public const COND_FROM_ADDRESS      = 'from_address';
    public const COND_SUBJECT_CONTAINS  = 'subject_contains';
    public const COND_HAS_ATTACHMENT    = 'has_attachment';
    public const COND_ATTACHMENT_TYPE   = 'attachment_type';
    public const COND_TO_ADDRESS        = 'to_address';

    // Action types
    public const ACTION_ASSIGN_CUSTOMER  = 'assign_customer';
    public const ACTION_ASSIGN_SUPPLIER  = 'assign_supplier';
    public const ACTION_SET_CATEGORY     = 'set_category';
    public const ACTION_SET_TAG          = 'set_tag';
    public const ACTION_SKIP_REVIEW      = 'skip_review';
    public const ACTION_SET_DIRECTION    = 'set_direction';

    protected $table = 'communication_rules';

    protected $fillable = [
        'company_id', 'name', 'description',
        'condition_type', 'condition_value',
        'action_type', 'action_value',
        'confidence_boost', 'priority', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($query): void
    {
        $query->where('active', true)->orderBy('priority');
    }

    public function conditionLabel(): string
    {
        return match ($this->condition_type) {
            self::COND_FROM_DOMAIN      => 'Von Domain',
            self::COND_FROM_ADDRESS     => 'Von Adresse',
            self::COND_SUBJECT_CONTAINS => 'Betreff enthält',
            self::COND_HAS_ATTACHMENT   => 'Hat Anhang',
            self::COND_ATTACHMENT_TYPE  => 'Anhang-Typ',
            self::COND_TO_ADDRESS       => 'An Adresse',
            default                     => $this->condition_type,
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action_type) {
            self::ACTION_ASSIGN_CUSTOMER => 'Kunden zuordnen',
            self::ACTION_ASSIGN_SUPPLIER => 'Lieferanten zuordnen',
            self::ACTION_SET_CATEGORY    => 'Kategorie setzen',
            self::ACTION_SET_TAG         => 'Tag setzen',
            self::ACTION_SKIP_REVIEW     => 'Prüfung überspringen',
            self::ACTION_SET_DIRECTION   => 'Richtung setzen',
            default                      => $this->action_type,
        };
    }
}
