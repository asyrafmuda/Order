<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\ComboOffer;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $stations = [];

    /** @var array<string, int> */
    private array $groups = [];

    public function run(): void
    {
        $this->seedStations();
        $this->seedModifierGroups();

        ComboOffer::query()->updateOrCreate(['name' => 'Combo Set'], [
            'description' => 'Seasoned Fries + Iced Soft Drink / Lemon Tea',
            'price' => 790,
            'choices' => ['Iced Soft Drink', 'Iced Lemon Tea'],
            'is_active' => true,
        ]);

        foreach ($this->menu() as $sort => $category) {
            $model = Category::query()->updateOrCreate(['slug' => Str::slug($category['name'])], [
                'name' => $category['name'],
                'icon' => $category['icon'],
                'eyebrow' => $category['eyebrow'],
                'tagline' => $category['tagline'],
                'kitchen_station_id' => $this->stations[$category['station']],
                'sort_order' => $sort,
                'is_active' => true,
            ]);

            foreach ($category['products'] as $productSort => $product) {
                $this->seedProduct($model, $product, $productSort);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedProduct(Category $category, array $data, int $sort): void
    {
        $variants = $data['variants'] ?? [];

        $product = Product::query()->updateOrCreate(['slug' => Str::slug($data['name'])], [
            'category_id' => $category->id,
            'kitchen_station_id' => isset($data['station']) ? $this->stations[$data['station']] : null,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $variants ? min(array_column($variants, 1)) : $data['price'],
            'status' => $data['status'] ?? ProductStatus::Available,
            'badge' => $data['badge'] ?? null,
            'tags' => $data['tags'] ?? [],
            'is_combo_eligible' => $data['combo'] ?? false,
            'is_upsell' => $data['upsell'] ?? false,
            'prep_minutes' => $data['prep'] ?? 10,
            'sort_order' => $sort,
        ]);

        $product->variants()->delete();

        foreach ($variants as $index => [$name, $price]) {
            $product->variants()->create([
                'name' => $name,
                'price' => $price,
                'is_default' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $product->modifierGroups()->sync(array_map(fn (string $group) => $this->groups[$group], $data['groups'] ?? []));
    }

    private function seedStations(): void
    {
        $stations = ['Hot Iron Grill', 'Deep Fryer', 'Bun & Roti John Padu', 'Wok & Pasta', 'Drinks & Packing'];

        foreach ($stations as $index => $name) {
            $this->stations[$name] = KitchenStation::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'sort_order' => $index,
                'is_active' => true,
            ])->id;
        }
    }

    private function seedModifierGroups(): void
    {
        $groups = [
            'Hold the...' => [0, 5, [['No Veggies', 0], ['No Sauce', 0], ['No Mayo', 0], ['No Onion', 0], ['No Pickles', 0]]],
            'A Little Extra' => [0, 3, [['Extra Mayo', 0], ['Extra Chili Sauce', 0], ['Extra Cheese Sauce', 150]]],
            'Burger Add-ons' => [0, 5, [
                ['Extra Beef Patty', 600], ['Extra Cheddar Slice', 200], ['Fried Sunny Egg', 150],
                ['Smoked Beef Bacon', 350], ['Extra Boss Sauce', 100],
            ]],
            'Choice of Sauce' => [1, 1, [['Black Pepper', 0], ['Mushroom', 0], ['Smoky BBQ', 0]]],
            'Spice Level' => [1, 1, [['Normal', 0], ['Pedas', 0], ['Extra Pedas', 0]]],
            'Roti John Extras' => [0, 3, [['Extra Cheese Leleh', 300], ['Extra Egg', 150], ['Extra Meat', 400]]],
        ];

        $sort = 0;

        foreach ($groups as $name => [$min, $max, $modifiers]) {
            $group = ModifierGroup::query()->updateOrCreate(['name' => $name], [
                'min_select' => $min,
                'max_select' => $max,
                'sort_order' => $sort++,
            ]);

            foreach ($modifiers as $index => [$modifierName, $price]) {
                $group->modifiers()->updateOrCreate(['name' => $modifierName], [
                    'price' => $price,
                    'is_available' => true,
                    'sort_order' => $index,
                ]);
            }

            $this->groups[$name] = $group->id;
        }
    }

    /**
     * Prices are in sen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function menu(): array
    {
        $burgerGroups = ['Hold the...', 'A Little Extra', 'Burger Add-ons'];

        return [
            [
                'name' => 'Burger', 'icon' => '🍔', 'eyebrow' => 'Grill Master Classics',
                'tagline' => 'Crisp crust smash patties & soft toasted brioche', 'station' => 'Hot Iron Grill',
                'products' => [
                    [
                        'code' => 'B1', 'name' => 'King Smash Beef Burger', 'badge' => "Chef's Pick",
                        'description' => 'Smash Beef Bacon + Fried Sunny Egg + Double Melted Cheddar & Signature Boss Sauce.',
                        'tags' => ['150g or 300g', 'Brioche'], 'combo' => true, 'groups' => $burgerGroups, 'prep' => 12,
                        'variants' => [['Patty 150g', 2090], ['Patty 300g', 2790]],
                    ],
                    [
                        'code' => 'B2', 'name' => 'Smash Beef Cheese Burger',
                        'description' => 'Crispy lacy edges beef patty, double melted American cheese slices, pickles and house sauce.',
                        'tags' => ['Patty 150g / 300g'], 'combo' => true, 'groups' => $burgerGroups,
                        'variants' => [['Patty 150g', 1490], ['Patty 300g', 2090]],
                    ],
                    [
                        'code' => 'B3', 'name' => 'Smash Beef Smoke Burger',
                        'description' => 'Smothered in hickory charcoal smoke glaze, sharp cheddar, smoked beef bacon and crispy onion.',
                        'tags' => ['Patty 150g / 300g'], 'combo' => true, 'groups' => $burgerGroups,
                        'variants' => [['Patty 150g', 1490], ['Patty 300g', 2090]],
                    ],
                    [
                        'code' => 'B4', 'name' => 'Super Deluxe Burger', 'badge' => 'Dual Protein', 'price' => 2090,
                        'description' => 'Colossal combo of smash beef patty + golden crispy buttermilk fried chicken thigh.',
                        'combo' => true, 'groups' => $burgerGroups, 'prep' => 14,
                    ],
                    [
                        'code' => 'B5 / B6', 'name' => 'Burger Ayam Goreng', 'station' => 'Deep Fryer',
                        'description' => 'Signature spiced crunchy chicken fillet with pickled gherkins and garlic tartar spread.',
                        'combo' => true, 'groups' => ['Hold the...', 'A Little Extra'],
                        'variants' => [['Single Fillet', 1390], ['Double Stack', 1990]],
                    ],
                    [
                        'code' => 'B7', 'name' => 'Burger Beef Brisket', 'price' => 4090,
                        'description' => '12-hour slow smoked tender brisket drenched in black pepper au jus.',
                        'status' => ProductStatus::ComingSoon,
                    ],
                ],
            ],
            [
                'name' => 'Roti John Padu 12-Inci', 'icon' => '🥖', 'eyebrow' => 'Street Legend Original',
                'tagline' => 'Foot-long egg-soaked loaf, grilled on the flat top', 'station' => 'Bun & Roti John Padu',
                'products' => [
                    ['code' => 'R1', 'name' => 'Roti John Ustaz Daging', 'price' => 1290, 'groups' => ['Hold the...', 'A Little Extra', 'Roti John Extras'],
                        'description' => 'Minced beef, egg, onion and our sweet chilli mayo drizzle on a 12-inch loaf.', 'badge' => 'Best Seller'],
                    ['code' => 'R2', 'name' => 'Roti John Ustaz Ayam', 'price' => 1190, 'groups' => ['Hold the...', 'A Little Extra', 'Roti John Extras'],
                        'description' => 'Spiced minced chicken and egg, folded hot with chilli and mayo.'],
                    ['code' => 'R3', 'name' => 'Roti John Cheese Leleh', 'price' => 1590, 'groups' => ['Hold the...', 'A Little Extra', 'Roti John Extras'],
                        'description' => 'Beef roti john flooded with melted cheddar cheese sauce.'],
                    ['code' => 'R4', 'name' => 'Roti John Kambing', 'price' => 1690, 'groups' => ['Hold the...', 'A Little Extra', 'Roti John Extras'],
                        'description' => 'Tender minced lamb with black pepper, egg and caramelised onion.'],
                ],
            ],
            [
                'name' => 'Sharing Plater', 'icon' => '🍽️', 'eyebrow' => 'Crowd Sizzlers',
                'tagline' => 'Great for groups', 'station' => 'Hot Iron Grill',
                'products' => [
                    ['code' => 'PL-A', 'name' => 'Plater A (2-3 pax)', 'price' => 3900, 'prep' => 18, 'badge' => '2-3 Pax',
                        'description' => 'Chicken Chop, Roti Ustaz, Spaghetti, Wedges',
                        'tags' => ['Grill Chicken Chop (1pc)', 'Roti John Ustaz Full Cut', 'Spaghetti Bolognese/Aglio', 'Cheese Wedges with Mayo Drizzle']],
                    ['code' => 'PL-B', 'name' => 'Plater B (3-4 pax)', 'price' => 7500, 'prep' => 22, 'badge' => '3-4 Pax Feast',
                        'description' => 'Chicken Chop, Lamb Chop, Roti Ustaz, Spaghetti, Wedges + Lemon Tea jug',
                        'tags' => ['Grill Chicken Chop (2pcs) + Grill Lamb Chop', 'Signature Roti John Ustaz', 'Spaghetti & Crispy Cheese Wedges', 'FREE Iced Lemon Tea jug included']],
                ],
            ],
            [
                'name' => 'Western & Grill', 'icon' => '🥩', 'eyebrow' => 'From the Hot Iron',
                'tagline' => 'Served with fries, coleslaw and your choice of sauce', 'station' => 'Hot Iron Grill',
                'products' => [
                    ['code' => 'W1', 'name' => 'Grill Chicken Chop', 'price' => 1890, 'groups' => ['Choice of Sauce'], 'prep' => 15,
                        'description' => 'Marinated boneless chicken thigh, flame grilled until charred and juicy.'],
                    ['code' => 'W2', 'name' => 'Grill Lamb Chop', 'price' => 3290, 'groups' => ['Choice of Sauce'], 'prep' => 18,
                        'description' => 'Two thick-cut lamb shoulder chops with rosemary butter.'],
                    ['code' => 'W3', 'name' => 'Fish & Chips', 'price' => 1790, 'station' => 'Deep Fryer',
                        'description' => 'Crispy battered dory fillet with tartar sauce and lemon.'],
                    ['code' => 'W4', 'name' => 'Chicken Grill Cheese', 'price' => 2190, 'groups' => ['Choice of Sauce'], 'prep' => 15,
                        'description' => 'Grilled chicken chop under a blanket of torched cheddar cheese.'],
                ],
            ],
            [
                'name' => 'Set Nasi Goreng', 'icon' => '🍚', 'eyebrow' => 'Wok Hei Favourites',
                'tagline' => 'Fried to order on a roaring wok', 'station' => 'Wok & Pasta',
                'products' => [
                    ['code' => 'N1', 'name' => 'Nasi Goreng Kampung Ayam Goreng', 'price' => 1290, 'groups' => ['Spice Level'],
                        'description' => 'Anchovy and kangkung fried rice with crispy fried chicken and sambal.'],
                    ['code' => 'N2', 'name' => 'Nasi Goreng USA', 'price' => 1590, 'groups' => ['Spice Level'],
                        'description' => 'Fried rice with paprik beef, prawns, squid and a sunny egg.'],
                    ['code' => 'N3', 'name' => 'Nasi Goreng Chicken Chop', 'price' => 1690, 'groups' => ['Spice Level', 'Choice of Sauce'],
                        'description' => 'Egg fried rice topped with a grilled chicken chop.'],
                ],
            ],
            [
                'name' => 'Spaghetti', 'icon' => '🍝', 'eyebrow' => 'Pasta Corner',
                'tagline' => 'Tossed hot in the pan', 'station' => 'Wok & Pasta',
                'products' => [
                    ['code' => 'S1', 'name' => 'Spaghetti Bolognese Beef', 'price' => 1390,
                        'description' => 'Slow-cooked minced beef ragu with parmesan.'],
                    ['code' => 'S2', 'name' => 'Spaghetti Aglio Olio Chicken', 'price' => 1390, 'groups' => ['Spice Level'],
                        'description' => 'Garlic, chilli flakes and olive oil with grilled chicken strips.'],
                    ['code' => 'S3', 'name' => 'Spaghetti Carbonara Smoked Beef', 'price' => 1590,
                        'description' => 'Creamy carbonara with smoked beef bacon and mushrooms.'],
                ],
            ],
            [
                'name' => 'Side Dish', 'icon' => '🍟', 'eyebrow' => 'Sides & Sips',
                'tagline' => 'Something crispy on the side', 'station' => 'Deep Fryer',
                'products' => [
                    ['code' => 'SD1', 'name' => 'Cheezy Wedges', 'price' => 690, 'upsell' => true, 'prep' => 6,
                        'description' => 'Golden potato wedges with extra cheese sauce drizzle on the side.'],
                    ['code' => 'SD2', 'name' => 'Seasoned Fries', 'price' => 590, 'upsell' => true, 'prep' => 5,
                        'description' => 'Shoestring fries dusted with our house seasoning.'],
                    ['code' => 'SD3', 'name' => 'Onion Rings', 'price' => 690, 'prep' => 6,
                        'description' => 'Thick-cut crispy battered onion rings.'],
                    ['code' => 'SD4', 'name' => 'Chicken Nuggets (6 pcs)', 'price' => 790, 'prep' => 6,
                        'description' => 'Crispy chicken nuggets with BBQ dip.'],
                    ['code' => 'D1', 'name' => 'Iced Lemon Tea', 'price' => 390, 'station' => 'Drinks & Packing', 'prep' => 2,
                        'description' => 'House-brewed black tea with fresh lemon.'],
                    ['code' => 'D2', 'name' => 'Iced Soft Drink', 'price' => 350, 'station' => 'Drinks & Packing', 'prep' => 1,
                        'description' => 'Ask the counter for today\'s flavours.'],
                ],
            ],
        ];
    }
}
