<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // JTL WaWi uses negative IDs (-33 to -1) for built-in system records.
        // These three tables receive negative PKs which MySQL rejects as bigint unsigned.
        // wawi_dbo_tdateityp.cDateityp is a string column (file extensions like .7zip)
        // misgenerated as bigint unsigned — the 'c' prefix in JTL naming means character.

        DB::statement('ALTER TABLE wawi_dbo_tcustomerqueryoverview
            MODIFY COLUMN kCustomerQueryOverview BIGINT NOT NULL,
            MODIFY COLUMN kParent BIGINT NULL');

        DB::statement('ALTER TABLE wawi_dbo_tgutschriftstornogrund
            MODIFY COLUMN kGutschriftStornogrund BIGINT NOT NULL');

        DB::statement('ALTER TABLE wawi_dbo_tdateityp
            MODIFY COLUMN cDateityp VARCHAR(100) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wawi_dbo_tcustomerqueryoverview
            MODIFY COLUMN kCustomerQueryOverview BIGINT UNSIGNED NOT NULL,
            MODIFY COLUMN kParent BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE wawi_dbo_tgutschriftstornogrund
            MODIFY COLUMN kGutschriftStornogrund BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE wawi_dbo_tdateityp
            MODIFY COLUMN cDateityp BIGINT UNSIGNED NOT NULL');
    }
};
