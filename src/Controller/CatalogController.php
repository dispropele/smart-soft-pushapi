<?php

namespace App\Controller;

use App\Entity\PledgedItem;
use App\Repository\PledgedItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CatalogController extends AbstractController
{
    #[Route('/', name: 'app_catalog')]
    public function index(Request $request, PledgedItemRepository $repo): Response
    {
        $search   = trim($request->query->get('q', ''));
        $sort     = $request->query->get('sort', 'date');
        $page     = max(1, (int)$request->query->get('page', 1));
        $perPage  = 20;

        $priceMin   = $request->query->get('price_min');
        $priceMax   = $request->query->get('price_max');
        $categoryId = $request->query->get('category');
        $goodTypeId = $request->query->get('type');

        $priceMin   = ($priceMin   !== null && $priceMin   !== '') ? (float)$priceMin   : null;
        $priceMax   = ($priceMax   !== null && $priceMax   !== '') ? (float)$priceMax   : null;
        $categoryId = ($categoryId !== null && $categoryId !== '') ? (int)$categoryId   : null;
        $goodTypeId = ($goodTypeId !== null && $goodTypeId !== '') ? (int)$goodTypeId   : null;

        $orderMap = [
            'date'       => ['publishedAt', 'DESC'],
            'price_asc'  => ['soldPrice',   'ASC'],
            'price_desc' => ['soldPrice',   'DESC'],
            'name'       => ['name',        'ASC'],
        ];
        [$orderField, $orderDir] = $orderMap[$sort] ?? $orderMap['date'];

        $items = $repo->findForCatalog(
            search:     $search,
            orderField: $orderField,
            orderDir:   $orderDir,
            page:       $page,
            perPage:    $perPage,
            priceMin:   $priceMin,
            priceMax:   $priceMax,
            categoryId: $categoryId,
            goodTypeId: $goodTypeId,
        );

        $total = $repo->countForCatalog($search, $priceMin, $priceMax, $categoryId, $goodTypeId);
        $pages = max(1, (int)ceil($total / $perPage));

        if ($page > $pages && $total > 0) {
            return $this->redirectToRoute('app_catalog', array_merge(
                $request->query->all(), ['page' => $pages]
            ));
        }

        // Категории только из активных предметов витрины
        $categories = $repo->createQueryBuilder('p')
            ->select('cat.id, cat.name')
            ->leftJoin('p.category', 'cat')
            ->where('p.status = :s')
            ->andWhere('cat.id IS NOT NULL')
            ->setParameter('s', PledgedItem::STATUS_FOR_SALE)
            ->groupBy('cat.id, cat.name')
            ->orderBy('cat.name', 'ASC')
            ->getQuery()->getResult();

        return $this->render('catalog/index.html.twig', [
            'items'      => $items,
            'categories' => $categories,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => $perPage,
            'search'     => $search,
            'sort'       => $sort,
            'priceMin'   => $priceMin,
            'priceMax'   => $priceMax,
            'categoryId' => $categoryId,
            'goodTypeId' => $goodTypeId,
        ]);
    }

    #[Route('/item/{id}', name: 'app_item_show')]
    public function show(int $id, PledgedItemRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item || !$item->isForSale()) {
            throw $this->createNotFoundException('Товар не найден');
        }
        return $this->render('catalog/show.html.twig', ['item' => $item]);
    }
}