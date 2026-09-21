<?php

declare(strict_types=1);

namespace StagasBites\Repository;

use StagasBites\Entity\Order;
use StagasBites\Entity\OrderStatus;

/**
 * @extends BaseRepository<Order>
 */
class OrderRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Order::class;
    }

    public function findByNumber(string $orderNumber): ?Order
    {
        return $this->findOneBy(['orderNumber' => strtoupper($orderNumber)]);
    }

    public function findByStripeSession(string $sessionId): ?Order
    {
        return $this->findOneBy(['stripeSessionId' => $sessionId]);
    }

    /**
     * @return list<Order>
     */
    public function findForUser(string $userId): array
    {
        return $this->repository->createQueryBuilder('o')
            ->where('o.userId = :uid')->setParameter('uid', $userId)
            ->andWhere('o.status != :pending')->setParameter('pending', OrderStatus::PENDING_PAYMENT)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()->getResult();
    }

    /**
     * @param array{status?: string, search?: string, date?: string} $filters
     *
     * @return array{items: list<Order>, total: int}
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $qb = $this->repository->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')->setParameter('status', $filters['status']);
        } else {
            $qb->andWhere('o.status != :pending')->setParameter('pending', OrderStatus::PENDING_PAYMENT);
        }
        if (!empty($filters['date'])) {
            $qb->andWhere('o.fulfilmentDate = :date')->setParameter('date', $filters['date']);
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(o.orderNumber) LIKE :q OR LOWER(o.customerEmail) LIKE :q OR LOWER(o.customerLastName) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(addcslashes($filters['search'], '%_')) . '%');
        }

        return $this->paginateQuery($qb, $page, $perPage);
    }

    /**
     * @return array{revenue_30d: int, orders_30d: int, open_orders: int, upcoming: list<Order>}
     */
    public function dashboardStats(): array
    {
        $paidStates = [OrderStatus::PAID, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::COMPLETED];
        $since = new \DateTimeImmutable('-30 days');

        $totals = $this->repository->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.total), 0) AS revenue, COUNT(o.id) AS n')
            ->where('o.status IN (:states)')->setParameter('states', $paidStates)
            ->andWhere('o.paidAt >= :since')->setParameter('since', $since)
            ->getQuery()->getSingleResult();

        $openStates = [OrderStatus::PAID, OrderStatus::PREPARING, OrderStatus::READY];
        $upcoming = $this->repository->createQueryBuilder('o')
            ->where('o.status IN (:states)')->setParameter('states', $openStates)
            ->orderBy('o.fulfilmentDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        $open = (int) $this->repository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status IN (:states)')->setParameter('states', $openStates)
            ->getQuery()->getSingleScalarResult();

        return [
            'revenue_30d' => (int) $totals['revenue'],
            'orders_30d' => (int) $totals['n'],
            'open_orders' => $open,
            'upcoming' => $upcoming,
        ];
    }
}
