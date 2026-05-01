<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\Communications\CommunicationRule;
use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;

class RuleEngineService
{
    /**
     * Evaluate all active rules against a communication payload.
     *
     * @param array $payload {
     *   from_address, to_addresses, subject, has_attachments, attachment_mimetypes,
     *   company_id
     * }
     * @param array $assignmentResult From AssignmentService::assign()
     *
     * @return array {
     *   communicable_type, communicable_id,
     *   dim_contact, dim_org, dim_role, dim_category, dim_document, dim_action, overall,
     *   rule_matches
     * }
     */
    public function evaluate(array $payload, array $assignmentResult): array
    {
        $rules = CommunicationRule::active()
            ->where(function ($q) use ($payload) {
                $q->whereNull('company_id')
                  ->orWhere('company_id', $payload['company_id'] ?? null);
            })
            ->get();

        $confidence = [
            'communicable_type' => $assignmentResult['communicable_type'],
            'communicable_id'   => $assignmentResult['communicable_id'],
            'dim_contact'       => $assignmentResult['dim_contact'],
            'dim_org'           => $assignmentResult['dim_org'],
            'dim_role'          => 0,
            'dim_category'      => 0,
            'dim_document'      => 0,
            'dim_action'        => 0,
            'overall'           => 0,
            'rule_matches'      => [],
        ];

        foreach ($rules as $rule) {
            if (!$this->matches($rule, $payload)) {
                continue;
            }

            $confidence['rule_matches'][] = [
                'rule_id'        => $rule->id,
                'rule_name'      => $rule->name,
                'condition_type' => $rule->condition_type,
                'action_type'    => $rule->action_type,
                'action_value'   => $rule->action_value,
                'boost'          => $rule->confidence_boost,
            ];

            $this->applyAction($rule, $payload, $confidence);
        }

        $confidence['overall'] = $this->calcOverall($confidence);

        return $confidence;
    }

    private function matches(CommunicationRule $rule, array $payload): bool
    {
        $value = $rule->condition_value;
        $from  = strtolower($payload['from_address'] ?? '');

        return match ($rule->condition_type) {
            CommunicationRule::COND_FROM_DOMAIN => str_ends_with($from, '@' . strtolower($value))
                                                || str_ends_with($from, '.' . strtolower($value)),
            CommunicationRule::COND_FROM_ADDRESS => $from === strtolower($value),
            CommunicationRule::COND_SUBJECT_CONTAINS => str_contains(
                strtolower($payload['subject'] ?? ''),
                strtolower($value)
            ),
            CommunicationRule::COND_HAS_ATTACHMENT => !empty($payload['has_attachments']),
            CommunicationRule::COND_ATTACHMENT_TYPE => in_array(
                strtolower($value),
                array_map('strtolower', $payload['attachment_mimetypes'] ?? [])
            ),
            CommunicationRule::COND_TO_ADDRESS => in_array(
                strtolower($value),
                array_map('strtolower', $payload['to_addresses'] ?? [])
            ),
            default => false,
        };
    }

    private function applyAction(CommunicationRule $rule, array $payload, array &$confidence): void
    {
        $boost = $rule->confidence_boost;

        switch ($rule->action_type) {
            case CommunicationRule::ACTION_ASSIGN_CUSTOMER:
                $id = (int) $rule->action_value;
                if ($id && Customer::find($id)) {
                    $confidence['communicable_type'] = Customer::class;
                    $confidence['communicable_id']   = $id;
                    $confidence['dim_org'] = min(100, $confidence['dim_org'] + $boost);
                }
                break;

            case CommunicationRule::ACTION_ASSIGN_SUPPLIER:
                $id = (int) $rule->action_value;
                if ($id && Supplier::find($id)) {
                    $confidence['communicable_type'] = Supplier::class;
                    $confidence['communicable_id']   = $id;
                    $confidence['dim_org'] = min(100, $confidence['dim_org'] + $boost);
                }
                break;

            case CommunicationRule::ACTION_SKIP_REVIEW:
                $confidence['dim_action'] = min(100, $confidence['dim_action'] + $boost);
                break;

            case CommunicationRule::ACTION_SET_CATEGORY:
                $confidence['dim_category'] = min(100, $confidence['dim_category'] + $boost);
                break;

            case CommunicationRule::ACTION_SET_TAG:
                // Tag attachment is handled by GmailImportService using rule_matches
                $confidence['dim_category'] = min(100, $confidence['dim_category'] + ($boost / 2));
                break;
        }
    }

    private function calcOverall(array $confidence): int
    {
        $dims = [
            $confidence['dim_contact'],
            $confidence['dim_org'],
            $confidence['dim_role'],
            $confidence['dim_category'],
            $confidence['dim_document'],
            $confidence['dim_action'],
        ];
        return (int) round(array_sum($dims) / 6);
    }
}
