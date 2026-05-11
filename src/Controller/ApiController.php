<?php

namespace App\Controller;

use App\Repository\GoodTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/category/{categoryId}/types', name: 'api_category_types', methods: ['GET'])]
    public function getCategoryTypes(int $categoryId, GoodTypeRepository $goodTypeRepository): JsonResponse
    {
        $types = $goodTypeRepository->createQueryBuilder('gt')
            ->where('gt.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('gt.name', 'ASC')
            ->select('gt.id', 'gt.name')
            ->getQuery()
            ->getArrayResult();

        return new JsonResponse($types);
    }
}
