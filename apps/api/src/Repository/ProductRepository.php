<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\Product;
use StagasBites\Entity\ProductOption;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Product::class;
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @param array{category?: string, search?: string, featured?: bool, sort?: string, include_unavailable?: bool, min_price?: ?int, max_price?: ?int, max_spice?: ?int, ids?: list<string>} $filters
     *
     * @return array{items: list<Product>, total: int}
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $qb = $this->repository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.options', 'o')->addSelect('o');

        if (empty($filters['include_unavailable'])) {
            $qb->andWhere('p.isAvailable = true');
        }
        if (!empty($filters['category'])) {
            $qb->andWhere('c.slug = :category')->setParameter('category', $filters['category']);
        }
        if (!empty($filters['featured'])) {
            $qb->andWhere('p.isFeatured = true');
        }
        if (!empty($filters['search'])) {
            // Names and categories match anywhere. Descriptions only match longer terms at the start of a
            // word, otherwise "pie" would drag in every dish described as "a piece of chicken".
            $term = mb_strtolower(addcslashes(trim($filters['search']), '%_'));
            $where = 'LOWER(p.name) LIKE :q OR LOWER(c.name) LIKE :q';
            $qb->setParameter('q', '%' . $term . '%');
            if (mb_strlen($term) >= 4) {
                $where .= ' OR LOWER(p.shortDescription) LIKE :qStart OR LOWER(p.shortDescription) LIKE :qWord';
                $qb->setParameter('qStart', $term . '%')->setParameter('qWord', '% ' . $term . '%');
            }
            $qb->andWhere($where);
        }

        if (!empty($filters['ids'])) {
            $qb->andWhere('p.id IN (:ids)')->setParameter('ids', $filters['ids']);
        }
        if (isset($filters['max_spice'])) {
            $qb->andWhere('p.spiceLevel <= :spice')->setParameter('spice', $filters['max_spice']);
        }
        // Price filters compare against the cheapest size, which is the "from" price shoppers see.
        // Each subquery needs its own alias: DQL rejects a repeated one when both bounds are set.
        $fromPrice = static fn (string $alias): string => sprintf('(SELECT MIN(%1$s.price) FROM %2$s %1$s WHERE %1$s.product = p)', $alias, ProductOption::class);
        if (isset($filters['min_price'])) {
            $qb->andWhere($fromPrice('lo') . ' >= :minPrice')->setParameter('minPrice', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $qb->andWhere($fromPrice('hi') . ' <= :maxPrice')->setParameter('maxPrice', $filters['max_price']);
        }

        $sort = $filters['sort'] ?? 'featured';
        if ($sort === 'price_asc' || $sort === 'price_desc') {
            // "Price" means the cheapest option, so sort on a correlated MIN() rather than in PHP after paging.
            $qb->addSelect('(SELECT MIN(po.price) FROM ' . ProductOption::class . ' po WHERE po.product = p) AS HIDDEN minPrice')
                ->orderBy('minPrice', $sort === 'price_asc' ? 'ASC' : 'DESC')
                ->addOrderBy('p.name', 'ASC');
        } elseif ($sort === 'featured' && !empty($filters['search'])) {
            // Searching: dishes whose name matches come before ones that only match on category or description.
            $qb->addSelect('(CASE WHEN LOWER(p.name) LIKE :q THEN 0 ELSE 1 END) AS HIDDEN relevance')
                ->orderBy('relevance', 'ASC')
                ->addOrderBy('p.isFeatured', 'DESC')
                ->addOrderBy('p.name', 'ASC');
        } else {
            match ($sort) {
                'name' => $qb->orderBy('p.name', 'ASC'),
                'newest' => $qb->orderBy('p.createdAt', 'DESC'),
                default => $qb->orderBy('p.isFeatured', 'DESC')->addOrderBy('p.sortOrder', 'ASC')->addOrderBy('p.name', 'ASC'),
            };
        }

        return $this->paginateQuery($qb, $page, $perPage);
    }

    /**
     * @return list<array{slug: string, updated_at: string|null}>
     */
    public function sitemapEntries(): array
    {
        $rows = $this->repository->createQueryBuilder('p')
            ->select('p.slug AS slug, p.updatedAt AS updatedAt')
            ->where('p.isAvailable = true')
            ->getQuery()->getArrayResult();

        return array_map(static fn (array $r): array => [
            'slug' => $r['slug'],
            'updated_at' => $r['updatedAt']?->format('Y-m-d'),
        ], $rows);
    }

    /**
     * @return array<string, int> category id => available product count
     */
    public function countByCategory(): array
    {
        $rows = $this->repository->createQueryBuilder('p')
            ->select('IDENTITY(p.category) AS cid, COUNT(p.id) AS n')
            ->where('p.isAvailable = true')
            ->groupBy('p.category')
            ->getQuery()->getArrayResult();

        return array_column($rows, 'n', 'cid');
    }
}
