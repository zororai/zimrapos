<?php

namespace Database\Seeders;

use App\Models\PanierTax;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZimraTaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
}
