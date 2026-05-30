<?php

declare(strict_types=1);

namespace Database\Factories\Knowledge;

use App\Models\Knowledge\KnowledgeQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeQuery>
 */
class KnowledgeQueryFactory extends Factory
{
    protected $model = KnowledgeQuery::class;

    public function definition(): array
    {
        return [
            'tenant_id'              => 'kolabri',
            'user_id'                => User::factory(),
            'question'               => fake()->sentence() . '?',
            'answer'                 => fake()->paragraph(),
            'question_type'          => KnowledgeQuery::TYPE_KNOWLEDGE,
            'used_internal_sources'  => true,
            'used_live_data'         => false,
            'has_customer_data'      => false,
            'has_invoice_data'       => false,
            'filter'                 => null,
            'source_count'           => 0,
        ];
    }
}
