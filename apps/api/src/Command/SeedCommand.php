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
        'small-chops' => ['Small Chops', 'Crisp, golden, party-ready bites — samosas, spring rolls, puff puff and more, fried fresh for your slot.', '6829.jpg'],
        'grills' => ['Grills', 'Smoky, pepper-kissed meats off the flame: asun, suya, wings, turkey and whole grilled tilapia.', '3974.jpg'],
        'snacks' => ['Snacks & Pastries', 'Buttery hand-made pies and rolls with generous, well-seasoned fillings. Baked the morning you collect.', '2852565.jpg'],
        'platters' => ['Platters & Bulk', 'A bit of everything, arranged to impress. Built for birthdays, office lunches and house parties.', '2646.jpg'],
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
                $category->fill(['name' => $name, 'slug' => $slug, 'description' => $description, 'image_url' => self::MEDIA . $image, 'sort_order' => $order]);
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
            'name' => $name, 'category' => $category, 'image_url' => self::MEDIA . $image, 'options' => $options,
            'short_description' => $short, 'description' => $description, 'spice_level' => $spice, 'is_featured' => $featured, 'tags' => $tags,
        ];

        return [
            $p('Samosas', 'small-chops', 'Screenshot-2026-08-20-122701.png', self::BITES, 'Crisp pastry triangles packed with spiced minced beef and vegetables.', "Hand-folded and fried to a shattering crunch, our samosas are filled with seasoned minced beef, potato and peas. A Nigerian party isn't a party without them.", 1, true, ['Party favourite']),
            $p('Spring Rolls', 'small-chops', 'Screenshot-2026-08-20-122845.png', self::BITES, 'Golden rolls stuffed with seasoned vegetables — the ones guests ask about.', 'Thin, blistered wrappers around a savoury filling of cabbage, carrot and aromatics. Customers tell us these are the best they have had.', 0, true, ['Best seller']),
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
            $p('Grilled Chicken Wings', 'grills', 'Screenshot-2026-08-20-132032.png', self::TRAY, 'Marinated overnight and grilled until smoky and juicy.', 'Wings marinated overnight in herbs and spices and grilled over flame. Milder than the peppered version — great for mixed crowds.', 1),
            $p('Grilled Chicken', 'grills', '37e329cc-1fe1-4b78-98d6-f44b08ff1898.jpg', self::TRAY, 'Juicy, well-seasoned chicken pieces with a flame-grilled char.', 'Drumsticks and thighs, deeply seasoned and grilled until the skin chars and the meat stays juicy.', 1),
            $p('Fried Chicken', 'grills', 'f4858eb2-17ae-40b9-a2b2-1b7124634c9f.jpg', self::TRAY, 'Nigerian-style fried chicken: seasoned to the bone, fried crisp.', 'Par-boiled in a seasoned broth so the flavour goes all the way through, then fried until golden. The drumsticks are a party favourite.', 0),
            $p('Snails — Spicy / Peppered', 'grills', '1b0310a0-ce4d-4c6d-9113-cf77ef4b7d73.jpg', [['10 pieces', 6000, null], ['20 pieces', 11000, null]], 'A Nigerian delicacy: tender giant snails in a hot pepper sauce.', 'Giant African land snails, cleaned and cooked until tender, then finished in a spicy pepper and onion sauce. A true celebration dish.', 3, false, ['Delicacy', 'Spicy']),
            $p('Grilled Tilapia with Side of Choice', 'grills', 'Screenshot-2026-08-20-123926.png', [['With fried yam', 3500, 'Serves 1–2'], ['With fried plantain', 3500, 'Serves 1–2'], ['With potato fries', 3500, 'Serves 1–2'], ['With sweet potato fries', 3500, 'Serves 1–2']], 'Whole tilapia, scored, marinated and grilled. Pick your side.', 'A whole tilapia, scored and marinated in pepper and herbs, grilled until the skin blisters. Comes with pepper sauce and your choice of side.', 2, true, ['Seafood']),
            $p('Assortment Platter', 'platters', 'Screenshot-2026-08-20-123846.png', [['Small', 10500, 'Serves 8–10'], ['Medium', 19000, 'Serves 15–20'], ['Large', 34000, 'Serves 30–35']], 'Some of every pastry and small chop, beautifully arranged.', "Can't decide? This platter brings together samosas, spring rolls, puff puff, shrimp, pies and more — arranged to look as good as it tastes. Ideal for birthdays and office gatherings.", 0, true, ['Best for events']),
        ];
    }
}
