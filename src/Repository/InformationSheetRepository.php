<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InformationSheet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InformationSheet>
 */
class InformationSheetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationSheet::class);
    }

    /**
     * @return InformationSheet[]
     */
    public function findForPublic(?string $category = null, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('sheet')
            ->orderBy('sheet.createdAt', 'DESC')
            ->addOrderBy('sheet.title', 'ASC');

        if ($category !== null && $category !== '') {
            $qb->andWhere('sheet.category = :category')
                ->setParameter('category', $category);
        }

        $tokens = $this->tokenizeQuery($query);
        foreach ($tokens as $index => $token) {
            $parameter = sprintf('search_%d', $index);
            $qb->andWhere(sprintf(
                '(LOWER(sheet.title) LIKE :%1$s OR LOWER(sheet.subtitle) LIKE :%1$s OR LOWER(sheet.thematic) LIKE :%1$s OR LOWER(sheet.contentMarkdown) LIKE :%1$s)',
                $parameter
            ))
                ->setParameter($parameter, '%' . $this->escapeLike($token) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByCategory(): array
    {
        $rows = $this->createQueryBuilder('sheet')
            ->select('sheet.category AS category, COUNT(sheet.id) AS total')
            ->groupBy('sheet.category')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(array_keys(InformationSheet::CATEGORY_LABELS), 0);
        foreach ($rows as $row) {
            $counts[$row['category']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return string[]
     */
    private function tokenizeQuery(?string $query): array
    {
        $query = trim(mb_strtolower((string) $query));
        if ($query === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_slice(array_unique($tokens), 0, 8));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
