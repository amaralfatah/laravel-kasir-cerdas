<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopOwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all owner users
        $owners = User::where('role', 'owner')->get();

        // Get all shops
        $shops = Shop::all();

        // Assign each shop a primary owner
        foreach ($shops as $index => $shop) {
            // Use modulo to cycle through owners if there are more shops than owners
            $ownerId = $owners[$index % count($owners)]->id;

            ShopOwner::create([
                'user_id' => $ownerId,
                'shop_id' => $shop->id,
                'is_primary_owner' => true,
                'notes' => 'Primary owner of the shop',
            ]);
        }

        // Add some secondary owners
        // First shop gets 2 secondary owners
        if ($shops->count() > 0 && $owners->count() > 1) {
            $firstShop = $shops->first();
            $secondaryOwners = $owners->where('id', '!=', $firstShop->shopOwners->first()->user_id)->take(2);

            foreach ($secondaryOwners as $owner) {
                ShopOwner::create([
                    'user_id' => $owner->id,
                    'shop_id' => $firstShop->id,
                    'is_primary_owner' => false,
                    'notes' => 'Secondary owner with partial management rights',
                ]);
            }
        }
    }
}
