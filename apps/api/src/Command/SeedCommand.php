<?php

declare(strict_types=1);

namespace StagasBites\Command;

use Doctrine\ORM\EntityManagerInterface;
use StagasBites\Entity\Category;
use StagasBites\Entity\Product;
use StagasBites\Helper\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seeds the catalogue carried over from the WordPress store. Idempotent: existing slugs are skipped.
 *
 * NOTE: prices are placeholders (the old site listed everything at a dummy $105) and images still
 * point at the old WordPress media library. Review both in the admin before launch.
 */
#[AsCommand(name: 'app:seed', description: 'Seed categories and products')]
final class SeedCommand extends Command
{
    private const MEDIA = 'https://midnightblue-crocodile-483501.hostingersite.com/wp-content/uploads/2026/08/';

    private const CATEGORIES = [
        'small-chops' => ['Small Chops', 'Crisp, golden, party-ready bites: samosas, spring rolls, puff puff and more, fried fresh for your slot.', '6829.jpg'],
        'grills' => ['Grills', 'Smoky, pepper-kissed meats off the flame: asun, suya, wings, turkey and whole grilled tilapia.', '3974.jpg'],
        'snacks' => ['Snacks & Pastries', 'Buttery hand-made pies and rolls with generous, well-seasoned fillings. Baked the morning you collect.', '2852565.jpg'],
        'platters' => ['Platters & Bulk', 'A bit of everything, arranged to impress. Built for birthdays, office lunches and house parties.', '2646.jpg'],
        'rice-mains' => ['Rice & Mains', 'Smoky party jollof, fried rice and hearty West African plates, by the plate or by the tray.', '/img/menu/jollof.jpg'],
        'soups-swallows' => ['Soups & Swallows', 'Slow-simmered soups and stews served with fufu, banku and other swallows.', '/img/menu/soup.jpg'],
        'sides-sauces' => ['Sides & Sauces', 'Fried plantain, yam, salads and our house pepper sauces to round out the order.', '/img/menu/plantain.jpg'],
        'drinks' => ['Drinks', 'Chilled, house-made West African drinks: zobo, ginger and tigernut.', '/img/menu/zobo.jpg'],
    ];

    private const TRAY = [['Small tray', 5500, 'Serves 4–6'], ['Medium tray', 10000, 'Serves 10–12'], ['Large tray', 18000, 'Serves 20–25']];
    private const PIES = [['Box of 6', 2400, null], ['Box of 12', 4500, null], ['Box of 24', 8500, null]];
    private const BITES = [['12 pieces', 1800, null], ['25 pieces', 3500, null], ['50 pieces', 6500, null]];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $categories = [];
        $order = 0;
        foreach (self::CATEGORIES as $slug => [$name, $description, $image]) {
            $category = $this->em->getRepository(Category::class)->findOneBy(['slug' => $slug]) ?? new Category();
            if ($category->getSlug() === '') {
                $category->fill(['name' => $name, 'slug' => $slug, 'description' => $description, 'image_url' => self::imageUrl($image), 'sort_order' => $order]);
                $this->em->persist($category);
            }
            $categories[$slug] = $category;
            ++$order;
        }

        $created = 0;
        foreach ($this->products() as $index => $row) {
            $slug = Str::slug($row['name']);
            if ($this->em->getRepository(Product::class)->findOneBy(['slug' => $slug]) !== null) {
                continue;
            }
            $product = new Product();
            $product->fill($row + ['slug' => $slug, 'sort_order' => $index, 'lead_time_hours' => 48]);
            $product->setCategory($categories[$row['category']]);
            $product->syncOptions(array_map(
                static fn (array $o, int $i): array => ['label' => $o[0], 'price' => $o[1], 'serves' => $o[2], 'is_default' => $i === 0],
                $row['options'],
                array_keys($row['options']),
            ));
            $this->em->persist($product);
            ++$created;
        }

        $this->em->flush();
        $output->writeln("<info>Seed complete: {$created} products created.</info>");

        return Command::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        $p = static fn (string $name, string $category, string $image, array $options, string $short, string $description, int $spice = 0, bool $featured = false, array $tags = []): array => [
            'name' => $name, 'category' => $category, 'image_url' => self::imageUrl($image), 'options' => $options,
            'short_description' => $short, 'description' => $description, 'spice_level' => $spice, 'is_featured' => $featured, 'tags' => $tags,
        ];

        return [
            $p('Samosas', 'small-chops', 'Screenshot-2026-08-20-122701.png', self::BITES, 'Crisp pastry triangles packed with spiced minced beef and vegetables.', "Hand-folded and fried to a shattering crunch, our samosas are filled with seasoned minced beef, potato and peas. A Nigerian party isn't a party without them.", 1, true, ['Party favourite']),
            $p('Spring Rolls', 'small-chops', 'Screenshot-2026-08-20-122845.png', self::BITES, 'Golden rolls stuffed with seasoned vegetables. The ones guests ask about.', 'Thin, blistered wrappers around a savoury filling of cabbage, carrot and aromatics. Customers tell us these are the best they have had.', 0, true, ['Best seller']),
            $p('Puff Puff', 'small-chops', '5f34ad56-7757-4145-9963-dbddf3419987.jpg', [['25 pieces', 1500, null], ['50 pieces', 2800, null], ['100 pieces', 5000, null]], 'Pillowy, lightly sweet dough balls, fried until deep gold.', 'The classic. Soft and airy inside, just-crisp outside, with a whisper of nutmeg. Kids and grown-ups go back for seconds.', 0, true, ['Vegetarian', 'Kid favourite']),
            $p('Shrimp Torpedos', 'small-chops', 'Screenshot-2026-08-20-120726.png', [['12 pieces', 2400, null], ['25 pieces', 4500, null]], 'Whole shrimp wrapped in a crisp pastry jacket.', 'Juicy tail-on shrimp, seasoned and wrapped in a thin wrapper, then fried until crackling. Serve with sweet chilli for dipping.', 0, false, ['Seafood']),
            $p('Scotch Eggs', 'snacks', 'a0e036c3-353a-4299-b01a-2655a0fab83a.jpg', [['Box of 6', 2400, null], ['Box of 12', 4500, null]], 'Boiled egg wrapped in seasoned sausage meat, breaded and fried.', 'A whole egg hugged by well-seasoned sausage meat and a crunchy crumb. Hearty, portable and perfect with a cold drink.'),
            $p('Sausage Rolls', 'snacks', 'Screenshot-2026-08-20-123431.png', [['Box of 12', 2400, null], ['Box of 24', 4500, null]], 'Flaky, buttery pastry rolled around seasoned sausage.', 'Nigerian-style sausage rolls with a tender, buttery crust and a peppery filling. Baked the morning of your order.'),
            $p('Meat Pie', 'snacks', 'a03739d7-fe42-4d98-860a-f3b411ed90df.jpg', self::PIES, 'The Nigerian classic: buttery shortcrust, minced beef, potato and carrot.', 'Rich shortcrust pastry crimped around a generous filling of seasoned minced beef, potato and carrot. The taste of home.', 0, true, ['Best seller']),
            $p('Chicken Pie', 'snacks', 'Screenshot-2026-08-20-125052.png', self::PIES, 'Tender chicken and vegetables in a golden shortcrust.', 'All the comfort of our meat pie, with a creamy, well-seasoned chicken filling.'),
            $p('Fish Pie', 'snacks', 'Screenshot-2026-08-20-125504.png', self::PIES, 'Flaked seasoned fish baked in buttery pastry.', 'Flaky fish gently spiced and folded into our signature shortcrust. Lighter than it looks, and very moreish.', 0, false, ['Seafood']),
            $p('Asun (Spicy Roast Goat Meat)', 'grills', 'Screenshot-2026-08-20-124713.png', [['Small tray', 6000, 'Serves 4–6'], ['Medium tray', 10500, 'Serves 10–12'], ['Large tray', 19000, 'Serves 20–25']], 'Smoky chopped goat tossed with scotch bonnet, onions and peppers.', 'Goat meat grilled over open flame until charred at the edges, then chopped and tossed in a fiery scotch-bonnet and onion sauce. Not shy on heat.', 3, true, ['Spicy', 'Signature']),
            $p('Turkey Suya', 'grills', '1b0310a0-ce4d-4c6d-9113-cf77ef4b7d731.jpg', self::TRAY, 'Flame-grilled turkey crusted in nutty, peppery yaji spice.', 'Turkey pieces marinated and grilled, then dusted in our house-ground suya spice of roasted peanut, ginger and chilli. Served with onions and tomato.', 2),
            $p('Whole Chicken Suya', 'grills', 'Screenshot-2026-08-20-123547.png', [['1 whole chicken', 3500, 'Serves 3–4'], ['2 whole chickens', 6500, 'Serves 6–8']], 'A whole bird, grilled and lacquered in suya spice.', 'A whole chicken, spatchcocked, grilled until the skin crackles, and finished with a heavy hand of suya spice.', 2, true),
            $p('Peppered Turkey', 'grills', 'Screenshot-2026-08-20-124922.png', self::TRAY, 'Fried turkey glazed in a rich, spicy pepper sauce.', 'Meaty turkey pieces, fried then simmered in a thick tomato, pepper and onion sauce until sticky and glossy.', 2),
            $p('Peppered Chicken Wings', 'grills', 'dc4c67de-7a2e-46fb-8b1d-93009166f7cc.jpg', self::TRAY, 'Crispy wings tossed in our pepper sauce.', 'Wings fried crisp, then tossed through a punchy pepper sauce. Customers say they carry the perfect amount of spice.', 2, false, ['Spicy']),
            $p('Grilled Chicken Wings', 'grills', 'Screenshot-2026-08-20-132032.png', self::TRAY, 'Marinated overnight and grilled until smoky and juicy.', 'Wings marinated overnight in herbs and spices and grilled over flame. Milder than the peppered version, so it is great for mixed crowds.', 1),
            $p('Grilled Chicken', 'grills', '37e329cc-1fe1-4b78-98d6-f44b08ff1898.jpg', self::TRAY, 'Juicy, well-seasoned chicken pieces with a flame-grilled char.', 'Drumsticks and thighs, deeply seasoned and grilled until the skin chars and the meat stays juicy.', 1),
            $p('Fried Chicken', 'grills', 'f4858eb2-17ae-40b9-a2b2-1b7124634c9f.jpg', self::TRAY, 'Nigerian-style fried chicken: seasoned to the bone, fried crisp.', 'Par-boiled in a seasoned broth so the flavour goes all the way through, then fried until golden. The drumsticks are a party favourite.', 0),
            $p('Peppered Snails', 'grills', '1b0310a0-ce4d-4c6d-9113-cf77ef4b7d73.jpg', [['10 pieces', 6000, null], ['20 pieces', 11000, null]], 'A Nigerian delicacy: tender giant snails in a hot pepper sauce.', 'Giant African land snails, cleaned and cooked until tender, then finished in a spicy pepper and onion sauce. A true celebration dish.', 3, false, ['Delicacy', 'Spicy']),
            $p('Grilled Tilapia with Side of Choice', 'grills', 'Screenshot-2026-08-20-123926.png', [['With fried yam', 3500, 'Serves 1–2'], ['With fried plantain', 3500, 'Serves 1–2'], ['With potato fries', 3500, 'Serves 1–2'], ['With sweet potato fries', 3500, 'Serves 1–2']], 'Whole tilapia, scored, marinated and grilled. Pick your side.', 'A whole tilapia, scored and marinated in pepper and herbs, grilled until the skin blisters. Comes with pepper sauce and your choice of side.', 2, true, ['Seafood']),
            $p('Assortment Platter', 'platters', 'Screenshot-2026-08-20-123846.png', [['Small', 10500, 'Serves 8–10'], ['Medium', 19000, 'Serves 15–20'], ['Large', 34000, 'Serves 30–35']], 'Some of every pastry and small chop, beautifully arranged.', "Can't decide? This platter brings together samosas, spring rolls, puff puff, shrimp, pies and more, arranged to look as good as it tastes. Ideal for birthdays and office gatherings.", 0, true, ['Best for events']),
            ...array_map(
                static fn (array $r): array => $p($r[0], $r[1], '/img/menu/' . $r[2] . '.jpg', $r[3], $r[4], $r[4], $r[5], false, $r[6]),
                self::extendedMenu(),
            ),
        ];
    }

    /** Old WordPress media is referenced by file name; photos shipped with the storefront by site-relative path. */
    private static function imageUrl(string $image): string
    {
        return str_starts_with($image, '/') ? $image : self::MEDIA . $image;
    }

    /**
     * Extended menu: [name, category, stock photo, options, description, spice, tags].
     * The photos are stock placeholders. Replace them with real ones in the admin.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: list<array{0: string, 1: int, 2: ?string}>, 4: string, 5: int, 6: list<string>}>
     */
    private static function extendedMenu(): array
    {
        $plate = [['Single plate', 1800, 'Serves 1'], ['Half tray', 6500, 'Serves 8 to 10'], ['Full tray', 12000, 'Serves 18 to 20']];
        $bowl = [['Single serving', 2800, 'Serves 1'], ['Family bowl', 7500, 'Serves 3 to 4']];
        $skewers = [['6 skewers', 1800, null], ['12 skewers', 3400, null], ['25 skewers', 6500, null]];
        $drink = [['Bottle (500 ml)', 700, null], ['Pack of 6', 3600, null]];
        $side = [['Regular', 900, 'Serves 1 to 2'], ['Party tray', 4000, 'Serves 8 to 10']];
        $one = static fn (string $label, int $price, ?string $serves = null): array => [[$label, $price, $serves]];

        return [
            // Small chops and snacks
            ['Mosa (Plantain Fritters)', 'small-chops', 'dough', [['Pack of 12', 1400, null], ['Party tray', 5000, 'Serves 10 to 12']], 'Soft, golden fritters made from ripe plantain. Sweet, savoury and hard to stop eating.', 0, ['Vegetarian']],
            ['Chin Chin', 'snacks', 'dough', [['Snack pack', 600, null], ['Pack of 4', 2000, null], ['Party tray', 5500, 'Serves 15 to 20']], 'Crunchy, lightly sweet fried dough bites. The classic Nigerian snack for any occasion.', 0, ['Vegetarian', 'Kid favourite']],
            ['Prawn Mayo Spring Rolls', 'small-chops', 'shrimp', [['10 pieces', 2200, null], ['Party tray', 9000, 'Serves 10 to 12']], 'Crisp spring rolls filled with prawns in a creamy mayo dressing.', 0, ['Seafood']],
            ['Coconut Shrimp', 'small-chops', 'shrimp', [['10 pieces', 2200, null], ['25 pieces', 5000, null]], 'Jumbo shrimp in a crunchy coconut crumb, served with a sweet chilli dip.', 0, ['Seafood']],
            ['Gizdodo', 'small-chops', 'stew', [['Small tray', 4500, 'Serves 4 to 6'], ['Large tray', 8500, 'Serves 10 to 12']], 'Peppered chicken gizzards tossed with fried plantain in a rich tomato and pepper sauce.', 2, ['Party favourite']],
            ['Kelewele (Spicy Fried Plantain)', 'small-chops', 'plantain', $side, 'Ripe plantain cubes marinated in ginger, chilli and spices, then fried until caramelised.', 2, ['Vegetarian']],
            ['Meat Pie and Drink Combo', 'snacks', 'zobo', $one('Combo', 1200, 'Serves 1'), 'One of our buttery meat pies with a chilled zobo or ginger drink.', 0, []],

            // Grills
            ['Chicken Suya Skewers', 'grills', 'skewers', $skewers, 'Chicken skewers grilled over flame and dusted with nutty, peppery suya spice.', 2, []],
            ['Chicken Kebab', 'grills', 'skewers', $skewers, 'Tender marinated chicken threaded with peppers and onions, grilled until juicy.', 1, []],
            ['Gizzard Sticks', 'grills', 'skewers', [['10 sticks', 1500, null], ['25 sticks', 3500, null]], 'Well-seasoned chicken gizzards, skewered and grilled until tender with a light char.', 2, []],
            ['Stick Meat (Beef Skewers)', 'grills', 'skewers', $skewers, 'Seasoned beef on a stick with peppers and onions. A Nigerian party essential.', 1, ['Party favourite']],
            ['Peppered Beef Skewers', 'grills', 'skewers', $skewers, 'Beef skewers finished in a hot scotch bonnet pepper glaze. One for the spice lovers.', 3, ['Spicy']],
            ['Beef Suya', 'grills', 'skewers', [['Small tray', 6500, 'Serves 4 to 6'], ['Large tray', 12000, 'Serves 10 to 12']], 'Thin-sliced beef, flame-grilled and coated in house-ground suya spice. Served with onions and tomato.', 2, ['Signature']],
            ['Peppered Goat Meat', 'grills', 'stew', [['5 pieces', 3500, null], ['Small tray', 9000, 'Serves 6 to 8']], 'Tender goat meat simmered, fried and tossed in a bold pepper sauce.', 3, ['Spicy']],
            ['Peppered Turkey Wings', 'grills', 'stew', [['3 wings', 3600, null], ['Small tray', 9500, 'Serves 6 to 8']], 'Meaty turkey wings fried and glazed in a sticky, spicy pepper sauce.', 2, []],
            ['Stewed Turkey', 'grills', 'stew', [['4 pieces', 1600, null], ['Small tray', 6000, 'Serves 4 to 6'], ['Large tray', 10000, 'Serves 10 to 12']], 'Assorted turkey pieces cooked down in a rich tomato and pepper stew.', 1, []],
            ['Prawn Kebab', 'grills', 'shrimp', [['5 skewers', 2200, null], ['12 skewers', 5000, null]], 'Juicy marinated prawns grilled on skewers with a squeeze of lemon.', 1, ['Seafood']],
            ['Grilled Catfish', 'grills', 'fish', $one('Whole fish', 3000, 'Serves 1 to 2'), 'Whole catfish marinated in pepper and herbs, grilled until smoky. Served with pepper sauce.', 2, ['Seafood']],
            ['Fried Fish', 'grills', 'fish', [['1 piece', 900, null], ['Small tray', 5500, 'Serves 6 to 8']], 'Seasoned fish fried until crisp outside and flaky inside.', 0, ['Seafood']],

            // Rice and mains
            ['Smoky Party Jollof Rice', 'rice-mains', 'jollof', $plate, 'Long-grain rice cooked down in a smoky tomato and pepper base, the way it is done at Nigerian parties.', 1, ['Best seller']],
            ['Nigerian Fried Rice', 'rice-mains', 'fried-rice', $plate, 'Fragrant fried rice with mixed vegetables, liver and shrimp, seasoned with curry and thyme.', 0, []],
            ['Jollof Rice with Chicken and Plantain', 'rice-mains', 'jollof', $one('Takeaway plate', 1600, 'Serves 1'), 'A full plate: smoky jollof rice, a piece of chicken and sweet fried plantain.', 1, ['Best seller']],
            ['Fried Rice and Chicken', 'rice-mains', 'fried-rice', $one('Takeaway plate', 1800, 'Serves 1'), 'Nigerian fried rice served with well-seasoned chicken.', 0, []],
            ['White Rice and Stew', 'rice-mains', 'rice2', $one('Takeaway plate', 1600, 'Serves 1'), 'Steamed white rice with a rich tomato and pepper stew and your choice of protein.', 1, []],
            ['Beans and Plantain', 'rice-mains', 'stew', $one('Takeaway plate', 1600, 'Serves 1'), 'Slow-cooked honey beans in palm oil sauce with sweet fried plantain.', 1, ['Vegetarian']],
            ['Yam and Palava Sauce', 'rice-mains', 'stew', $one('Takeaway plate', 2200, 'Serves 1'), 'Boiled yam with a hearty spinach and egusi sauce, served with fish.', 1, []],
            ['Waakye', 'rice-mains', 'rice2', $one('Takeaway plate', 2200, 'Serves 1'), 'Ghanaian rice and beans served with stew, gari, spaghetti, egg and your choice of fish or chicken.', 1, []],
            ['Attieke and Grilled Tilapia', 'rice-mains', 'fish', $one('Plate', 2800, 'Serves 1 to 2'), 'Fluffy cassava couscous with a whole grilled tilapia, fresh onion and tomato, and pepper sauce.', 2, ['Seafood']],
            ['Banga Soup with Starch', 'rice-mains', 'soup', $bowl, 'Rich palm nut soup from the Niger Delta with assorted meat and fish, served with starch.', 2, []],

            // Soups and swallows
            ['Fufu and Light Soup', 'soups-swallows', 'soup', $bowl, 'Smooth pounded fufu in a peppery tomato light soup with goat, beef or chicken.', 2, []],
            ['Fufu and Groundnut Soup', 'soups-swallows', 'soup', $bowl, 'Fufu served in a creamy, nutty groundnut soup with tender meat.', 1, []],
            ['Banku and Okra Stew', 'soups-swallows', 'soup', $bowl, 'Fermented corn and cassava dumplings with a rich okra stew, meat and fish.', 1, []],
            ['Banku and Grilled Tilapia', 'soups-swallows', 'fish', $one('Plate', 3000, 'Serves 1 to 2'), 'Banku with a whole grilled tilapia, fresh pepper sauce and shito.', 2, ['Seafood']],
            ['Rice Balls and Groundnut Soup', 'soups-swallows', 'soup', $bowl, 'Soft rice balls (omo tuo) in a rich peanut soup.', 1, []],
            ['Tuo Zaafi', 'soups-swallows', 'soup', $bowl, 'A northern Ghanaian favourite: soft corn swallow with ayoyo soup and stew.', 1, []],
            ['Ampesi and Kontomire Stew', 'soups-swallows', 'stew', $bowl, 'Boiled yam and plantain with a cocoyam leaf stew, egg and fish.', 1, []],
            ['Kokonte and Groundnut Soup', 'soups-swallows', 'soup', $bowl, 'Cassava flour swallow paired with a nutty groundnut soup.', 1, []],
            ['Fufu and Abunuabunu', 'soups-swallows', 'soup', $bowl, 'Fufu in a green cocoyam leaf soup with mushrooms, snails and meat.', 1, []],

            // Sides and sauces
            ['Fried Plantain (Dodo)', 'sides-sauces', 'plantain', $side, 'Ripe plantain fried until sweet, golden and caramelised at the edges.', 0, ['Vegetarian']],
            ['Fried Yam', 'sides-sauces', 'plantain', $side, 'Crisp-edged fried yam, perfect with pepper sauce.', 0, ['Vegetarian']],
            ['House Pepper Sauce', 'sides-sauces', 'sauce', [['Pack of 3 dips', 600, null], ['Jar (16 oz)', 2200, null]], 'Our signature scotch bonnet pepper sauce. Spoon it over everything.', 3, ['Spicy']],
            ['Shito (Black Pepper Sauce)', 'sides-sauces', 'sauce', $one('Jar (8 oz)', 1200), 'Ghanaian hot pepper sauce slow-cooked with dried fish, shrimp and spices.', 3, ['Spicy']],
            ['Green Chilli Sauce', 'sides-sauces', 'sauce', $one('Jar (8 oz)', 1200), 'Fresh, bright green chilli sauce with ginger and herbs.', 3, ['Spicy', 'Vegetarian']],
            ['Coleslaw', 'sides-sauces', 'salad', $side, 'Creamy, crunchy cabbage and carrot slaw that cools down the heat.', 0, ['Vegetarian']],
            ['Garden Salad', 'sides-sauces', 'salad', [['Regular', 1200, 'Serves 1 to 2'], ['Party tray', 4500, 'Serves 8 to 10']], 'Nigerian-style salad with lettuce, cabbage, carrot, sweetcorn, egg and a creamy dressing.', 0, ['Vegetarian']],
            ['Extra Rice', 'sides-sauces', 'rice2', $one('Portion', 700, 'Serves 1'), 'An extra portion of jollof, fried or white rice.', 0, []],
            ['Extra Chicken', 'sides-sauces', 'stew', $one('Piece', 700), 'An extra piece of seasoned fried or grilled chicken.', 0, []],
            ['Extra Fish', 'sides-sauces', 'fish', $one('Piece', 900), 'An extra piece of fried fish.', 0, ['Seafood']],
            ['Fried Turkey Tail (Tsofi)', 'sides-sauces', 'stew', $one('Portion', 900), 'Crisp, indulgent fried turkey tail. A street food favourite.', 0, []],

            // Drinks
            ['Zobo (Hibiscus Drink)', 'drinks', 'zobo', $drink, 'Chilled hibiscus drink brewed with ginger, pineapple and cloves. Also known as sobolo.', 0, ['Vegetarian']],
            ['Ginger Drink', 'drinks', 'zobo', $drink, 'A fiery, refreshing fresh ginger drink with lemon.', 0, ['Vegetarian']],
            ['Tigernut Milk (Kunun Aya)', 'drinks', 'zobo', $drink, 'Creamy, naturally sweet tigernut milk with dates and coconut. Served cold.', 0, ['Vegetarian']],
            ['Lamugin (Spiced Rice Drink)', 'drinks', 'zobo', $drink, 'A lightly sweet, spiced rice and ginger drink.', 0, ['Vegetarian']],
            ['Fresh Fruit Juice', 'drinks', 'zobo', $drink, 'Freshly pressed seasonal fruit juice with nothing added.', 0, ['Vegetarian']],

            // Platters
            ['Celebration Small Chops Platter', 'platters', 'platter', [['Bronze', 7000, 'Serves 6 to 8'], ['Silver', 10000, 'Serves 10 to 12'], ['Gold', 13000, 'Serves 14 to 16'], ['Grand', 15000, 'Serves 18 to 20']], 'A generous mix of samosas, spring rolls, stick meat, wings, mosa and puff puff. Bigger tiers add pies, sausage rolls, gizzard and shrimp.', 1, ['Best for events']],
            ['Feast for Four', 'platters', 'platter', $one('Platter', 12400, 'Serves 4'), 'Jollof and fried rice, grilled chicken, plantain, small chops and salad. Dinner for four, sorted.', 1, []],
            ['Date Night Tray', 'platters', 'platter', $one('Tray', 9400, 'Serves 2'), 'A sharing tray for two with rice, grilled protein, plantain, small chops and a drink each.', 1, []],
            ['Game Day Feast', 'platters', 'platter', $one('Platter', 9700, 'Serves 4 to 6'), 'Wings, suya, stick meat, samosas, spring rolls and puff puff. Built for match day.', 2, []],
        ];
    }
}
