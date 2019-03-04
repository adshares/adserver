<?php

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class ReplaceJsEnabledKey extends Migration
{
    public function up(): void
    {
        DB::update(<<<SQL
UPDATE sites
SET
  site_requires = REPLACE(site_requires,
                          'js_enabled',
                          'jsenabled');
SQL
        );
        DB::update(<<<SQL
UPDATE sites
SET
  site_excludes = REPLACE(site_excludes,
                          'js_enabled',
                          'jsenabled');
SQL
        );
    }

    public function down(): void
    {
        // Lack of rollback is intended.
    }
}
