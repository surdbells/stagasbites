<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use StagasBites\Exception\ApiException;

/**
 * @template T of object
 */
abstract class BaseRepository
{
    /** @var EntityRepository<T> */
    protected EntityRepository $repository;

    public function __construct(protected readonly EntityManagerInterface $em)
    {
        $this->repository = $em->getRepository($this->getEntityClass());
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    /**
     * @return T|null
     */
    public function find(string $id): ?object
    {
        return $this->repository->find($id);
    }

    /**
     * @return T
     */
    public function findOrFail(string $id): object
    {
        return $this->find($id) ?? throw ApiException::notFound();
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return T|null
     */
    public function findOneBy(array $criteria): ?object
    {
        return $this->repository->findOneBy($criteria);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<T>
     */
    public function findBy(array $criteria = [], array $orderBy = []): array
    {
        return array_values($this->repository->findBy($criteria, $orderBy));
    }

    /**
     * @param T $entity
     */
    public function save(object $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * @param T $entity
     */
    public function remove(object $entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    /**
     * @return array{items: list<T>, total: int}
     */
    protected function paginateQuery(QueryBuilder $qb, int $page, int $perPage): array
    {
        $qb->setFirstResult((max(1, $page) - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return ['items' => array_values(iterator_to_array($paginator)), 'total' => count($paginator)];
    }
}
