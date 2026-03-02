<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductLmivVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WP-15 – LMIV versioning logic for base-item products.
 *
 * Rules:
 *  1.  Each base-item product maintains a list of LMIV versions.
 *  2.  At most ONE version per product may have status = "active".
 *  3.  When an EAN changes (or a new EAN is assigned for the first time):
 *        a. The current active version (if any) is archived
 *           (effective_to = now, status = archived).
 *        b. A NEW version is created with the new EAN, copying the previous
 *           data_json as the starting point and incrementing version_number.
 *        c. The new version starts with status = "active" immediately.
 *  4.  Updating LMIV data (data_json) on the active version is possible
 *      without a rollover as long as the EAN does not change.
 *  5.  An admin can create a manual version (draft) for advance preparation.
 *  6.  Activating a draft version closes the current active version.
 */
final class LmivVersioningService
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Called when the primary EAN of a base-item product changes.
     *
     * Closes the current active version and opens a new one with the new EAN.
     * The data_json of the old version is copied to the new version as a
     * starting point so editors do not start from scratch.
     *
     * @param  array<string, mixed>|null $overrideData  If provided, used instead of copying old data
     * @param  int|null                  $actorUserId   Admin user triggering the change
     */
    public function onEanChange(
        Product $product,
        string  $newEan,
        ?string $changeReason  = null,
        ?array  $overrideData  = null,
        ?int    $actorUserId   = null,
    ): ProductLmivVersion {
        if (! $product->is_base_item) {
            throw new \InvalidArgumentException(
                "Product #{$product->getKey()} is not a base item and cannot have LMIV versions."
            );
        }

        return DB::transaction(function () use (
            $product, $newEan, $changeReason, $overrideData, $actorUserId
        ): ProductLmivVersion {
            $now = Carbon::now();

            // 1. Fetch and close the current active version (if any)
            $oldVersion = $this->findActiveVersion($product);
            $inheritedData = null;

            if ($oldVersion !== null) {
                if ($oldVersion->ean === $newEan) {
                    // EAN is unchanged — nothing to do
                    return $oldVersion;
                }

                $inheritedData = $oldVersion->data_json;

                $oldVersion->status       = ProductLmivVersion::STATUS_ARCHIVED;
                $oldVersion->effective_to = $now;
                $oldVersion->save();
            }

            // 2. Create the new version
            $nextVersion = $this->nextVersionNumber($product);

            $newVersion = ProductLmivVersion::create([
                'product_id'         => $product->getKey(),
                'version_number'     => $nextVersion,
                'ean'                => $newEan,
                'status'             => ProductLmivVersion::STATUS_ACTIVE,
                'data_json'          => $overrideData ?? $inheritedData,
                'change_reason'      => $changeReason ?? 'EAN-Änderung',
                'effective_from'     => $now,
                'effective_to'       => null,
                'created_by_user_id' => $actorUserId,
            ]);

            Log::info('LMIV version rolled over', [
                'product_id'     => $product->getKey(),
                'new_version'    => $nextVersion,
                'new_ean'        => $newEan,
                'old_version_id' => $oldVersion?->id,
            ]);

            return $newVersion;
        });
    }

    /**
     * Update the data_json on the currently active (or draft) version.
     *
     * Does NOT trigger a version rollover — use onEanChange() for that.
     *
     * @param  array<string, mixed> $data
     */
    public function updateData(
        Product $product,
        array   $data,
        ?int    $actorUserId = null,
    ): ProductLmivVersion {
        $version = $this->findActiveVersion($product)
                ?? $this->findDraftVersion($product);

        if ($version === null) {
            // No existing version — create the first one without an EAN
            return $this->createFirstVersion($product, null, $data, $actorUserId);
        }

        $version->data_json = $data;
        $version->save();

        return $version;
    }

    /**
     * Create a manual DRAFT version for advance preparation.
     *
     * The draft stays inactive until explicitly activated via activateDraft().
     *
     * @param  array<string, mixed>|null $data
     */
    public function createManualVersion(
        Product $product,
        ?string $ean           = null,
        ?array  $data          = null,
        ?string $changeReason  = null,
        ?int    $actorUserId   = null,
    ): ProductLmivVersion {
        if (! $product->is_base_item) {
            throw new \InvalidArgumentException(
                "Product #{$product->getKey()} is not a base item."
            );
        }

        return DB::transaction(function () use (
            $product, $ean, $data, $changeReason, $actorUserId
        ): ProductLmivVersion {
            $active  = $this->findActiveVersion($product);
            $inherit = $active?->data_json;

            $nextVersion = $this->nextVersionNumber($product);

            return ProductLmivVersion::create([
                'product_id'         => $product->getKey(),
                'version_number'     => $nextVersion,
                'ean'                => $ean ?? $active?->ean,
                'status'             => ProductLmivVersion::STATUS_DRAFT,
                'data_json'          => $data ?? $inherit,
                'change_reason'      => $changeReason ?? 'Manueller Entwurf',
                'effective_from'     => null,
                'effective_to'       => null,
                'created_by_user_id' => $actorUserId,
            ]);
        });
    }

    /**
     * Activate a DRAFT version.
     * Closes the current active version, then publishes the draft.
     */
    public function activateDraft(
        ProductLmivVersion $draft,
        ?int               $actorUserId = null,
    ): ProductLmivVersion {
        if (! $draft->isDraft()) {
            throw new \InvalidArgumentException(
                "Version #{$draft->id} is not a draft (status: {$draft->status})."
            );
        }

        return DB::transaction(function () use ($draft, $actorUserId): ProductLmivVersion {
            $now = Carbon::now();

            /** @var Product $product */
            $product = $draft->product;

            // Close the current active version
            $active = $this->findActiveVersion($product);
            if ($active !== null) {
                $active->status       = ProductLmivVersion::STATUS_ARCHIVED;
                $active->effective_to = $now;
                $active->save();
            }

            // Publish the draft
            $draft->status           = ProductLmivVersion::STATUS_ACTIVE;
            $draft->effective_from   = $now;
            $draft->effective_to     = null;
            $draft->created_by_user_id = $actorUserId ?? $draft->created_by_user_id;
            $draft->save();

            return $draft;
        });
    }

    /**
     * Ensure a base-item product has at least one active LMIV version.
     *
     * Creates an empty active version when none exists yet.
     * Idempotent: does nothing when an active version already exists.
     */
    public function ensureActiveVersion(
        Product $product,
        ?int    $actorUserId = null,
    ): ProductLmivVersion {
        $existing = $this->findActiveVersion($product);

        if ($existing !== null) {
            return $existing;
        }

        return $this->createFirstVersion($product, null, null, $actorUserId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findActiveVersion(Product $product): ?ProductLmivVersion
    {
        return ProductLmivVersion::where('product_id', $product->getKey())
            ->where('status', ProductLmivVersion::STATUS_ACTIVE)
            ->first();
    }

    private function findDraftVersion(Product $product): ?ProductLmivVersion
    {
        return ProductLmivVersion::where('product_id', $product->getKey())
            ->where('status', ProductLmivVersion::STATUS_DRAFT)
            ->orderByDesc('version_number')
            ->first();
    }

    private function nextVersionNumber(Product $product): int
    {
        $max = ProductLmivVersion::where('product_id', $product->getKey())
            ->max('version_number');

        return ($max === null) ? 1 : (int) $max + 1;
    }

    /**
     * @param  array<string, mixed>|null $data
     */
    private function createFirstVersion(
        Product $product,
        ?string $ean,
        ?array  $data,
        ?int    $actorUserId,
    ): ProductLmivVersion {
        return ProductLmivVersion::create([
            'product_id'         => $product->getKey(),
            'version_number'     => 1,
            'ean'                => $ean,
            'status'             => ProductLmivVersion::STATUS_ACTIVE,
            'data_json'          => $data,
            'change_reason'      => 'Erstanlage',
            'effective_from'     => Carbon::now(),
            'effective_to'       => null,
            'created_by_user_id' => $actorUserId,
        ]);
    }
}
