<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ManualPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ManualPage> */
class ManualPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManualPage::class);
    }

    /**
     * @return ManualPage[]
     */
    public function search(?string $query): array
    {
        $qb = $this->createQueryBuilder('page')
            ->addSelect('section')
            ->join('page.section', 'section')
            ->orderBy('section.position', 'ASC')
            ->addOrderBy('page.position', 'ASC')
            ->addOrderBy('page.title', 'ASC');

        foreach ($this->tokenizeQuery($query) as $index => $token) {
            $parameter = sprintf('search_%d', $index);
            $qb->andWhere(sprintf(
                '(LOWER(page.title) LIKE :%1$s OR LOWER(page.summary) LIKE :%1$s OR LOWER(page.contentMarkdown) LIKE :%1$s OR page.tags LIKE :%1$s)',
                $parameter
            ))
                ->setParameter($parameter, '%' . $this->escapeLike($token) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return string[] */
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
