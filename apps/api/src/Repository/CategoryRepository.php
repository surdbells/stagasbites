<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\Category;

/**
 * @extends BaseRepository<Category>
 */
class CategoryRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Category::class;
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<Category>
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']);
    }
}
