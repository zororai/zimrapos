<?php

use App\Models\PanierTax;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('panier_taxes', function (Blueprint $table) {
            $table->unsignedInteger('zimra_tax_id')->nullable()->after('code');
        });

        // Seed default ZIMRA tax types
        foreach (PanierTax::ZIMRA_TAX_TYPES as $taxType) {
            PanierTax::firstOrCreate(
                ['zimra_tax_id' => $taxType['zimra_tax_id']],
                [
                    'panier_id' => Str::ulid()->toString(),
                    'name' => $taxType['name'],
                    'percentage' => $taxType['percentage'],
                    'code' => $taxType['code'],
                    'panier_data' => $taxType,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panier_taxes', function (Blueprint $table) {
            $table->dropColumn('zimra_tax_id');
        });
    }
};
