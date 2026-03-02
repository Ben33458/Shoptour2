<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Catalog\Gebinde;
use App\Models\Catalog\PfandSet;
use App\Models\Catalog\PfandSetComponent;

/**
 * Recursively sums the deposit (Pfand) obligation for a Gebinde.
 *
 * PfandSet tree structure:
 *   PfandSet
 *     └─ PfandSetComponent[]   (each has qty)
 *          ├─ pfand_item_id set   → leaf: adds pfandItem.wert_brutto_milli × qty
 *          └─ child_pfand_set_id  → nested: recurse into child set, multiply result by qty
 *
 * Cycle protection:
 *   $visited set tracks PfandSet IDs already entered in the current call stack.
 *   If a set is visited twice, the branch returns 0 and logs a warning.
 *
 * All amounts are milli-cents (int). 1 EUR = 1_000_000 milli-cents.
 */
class PfandCalculator
{
    /**
     * Calculate the total deposit amount (brutto, milli-cents) for a Gebinde.
     *
     * Returns 0 when the Gebinde has no PfandSet or the set has no components.
     *
     * @param  Gebinde $gebinde   Must have pfand_set_id set (it always does per schema NOT NULL)
     * @return int                Total deposit in milli-cents (brutto)
     */
    public function totalForGebinde(Gebinde $gebinde): int
    {
        // Load the PfandSet with its components and their leaf items eagerly
        $pfandSet = PfandSet::with([
            'components.pfandItem',
            'components.childPfandSet',
        ])->find($gebinde->pfand_set_id);

        if ($pfandSet === null) {
            return 0;
        }

        return $this->sumSet($pfandSet, visited: []);
    }

    // -------------------------------------------------------------------------
    // Internal recursive logic
    // -------------------------------------------------------------------------

    /**
     * Recursively sum the deposit total for a PfandSet.
     *
     * @param  PfandSet         $set
     * @param  array<int, true> $visited   PfandSet IDs already on the call stack
     * @return int                         milli-cents (brutto)
     */
    private function sumSet(PfandSet $set, array $visited): int
    {
        if (isset($visited[$set->id])) {
            \Illuminate\Support\Facades\Log::warning(
                'Circular PfandSet reference detected',
                ['pfand_set_id' => $set->id, 'name' => $set->name]
            );

            return 0;
        }

        $visited[$set->id] = true;

        $total = 0;

        // Ensure components are loaded
        /** @var \Illuminate\Database\Eloquent\Collection<int, PfandSetComponent> $components */
        $components = $set->relationLoaded('components')
            ? $set->components
            : $set->components()->with(['pfandItem', 'childPfandSet'])->get();

        foreach ($components as $component) {
            if ($component->isLeaf()) {
                // Leaf: directly add the item's brutto value × qty
                $item = $component->pfandItem;

                if ($item === null) {
                    // Defensive: relation missing despite isLeaf() — skip gracefully
                    continue;
                }

                $total += $item->wert_brutto_milli * $component->qty;
            } elseif ($component->isNestedSet()) {
                // Nested: load the child set and recurse
                $childSet = $component->childPfandSet;

                if ($childSet === null) {
                    continue;
                }

                // Load the child's components if not already eager-loaded
                if (! $childSet->relationLoaded('components')) {
                    $childSet->load(['components.pfandItem', 'components.childPfandSet']);
                }

                $childTotal = $this->sumSet($childSet, $visited);
                $total     += $childTotal * $component->qty;
            }
            // If neither isLeaf nor isNestedSet: invalid component — skip silently
        }

        return $total;
    }
}
