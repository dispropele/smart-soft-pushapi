<?php

namespace App\Controller;

use App\Repository\GoodRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class CatalogController extends AbstractController
{
    #[Route('/', name: 'app_catalog')]
    public function index(Request $request, GoodRepository $goodRepository): Response
    {
        $search   = trim($request->query->get('q', ''));
        $sort     = $request->query->get('sort', 'date');
        $page     = max(1, (int) $request->query->get('page', 1));
        $perPage  = 20;

        // Фильтры
        $priceMin   = $request->query->get('price_min');
        $priceMax   = $request->query->get('price_max');
        $categoryId = $request->query->get('category');
        $goodTypeId = $request->query->get('type');

        $priceMin = $priceMin !== null && $priceMin !== '' ? (float) $priceMin : null;
        $priceMax = $priceMax !== null && $priceMax !== '' ? (float) $priceMax : null;
        $categoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        $goodTypeId = $goodTypeId !== null && $goodTypeId !== '' ? (int) $goodTypeId : null;

        $orderMap = [
            'date'       => ['statusDate', 'DESC'],
            'price_asc'  => ['soldPrice',  'ASC'],
            'price_desc' => ['soldPrice',  'DESC'],
            'name'       => ['name',       'ASC'],
        ];
        [$orderField, $orderDir] = $orderMap[$sort] ?? $orderMap['date'];

        $goods = $goodRepository->findForCatalog(
            search: $search,
            orderField: $orderField,
            orderDir: $orderDir,
            page: $page,
            perPage: $perPage,
            priceMin: $priceMin,
            priceMax: $priceMax,
            categoryId: $categoryId,
            goodTypeId: $goodTypeId,
        );

        $total = $goodRepository->countForCatalog(
            search: $search,
            priceMin: $priceMin,
            priceMax: $priceMax,
            categoryId: $categoryId,
            goodTypeId: $goodTypeId,
        );

        // Получаем все категории для фильтра
        $categories = $goodRepository->createQueryBuilder('g')
            ->select('cat.id, cat.name')
            ->leftJoin('g.category', 'cat')
            ->where('g.status = :status')
            ->andWhere('cat.id IS NOT NULL')
            ->setParameter('status', \App\Entity\Good::STATUS_ACTIVE)
            ->groupBy('cat.id, cat.name')
            ->orderBy('cat.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('catalog/index.html.twig', [
            'goods'      => $goods,
            'categories' => $categories,
            'total'      => $total,
            'page'       => $page,
            'pages'      => (int) ceil($total / $perPage),
            'perPage'    => $perPage,
            'search'     => $search,
            'sort'       => $sort,
            'priceMin'   => $priceMin,
            'priceMax'   => $priceMax,
            'categoryId' => $categoryId,
            'goodTypeId' => $goodTypeId,
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_show')]
    public function show(int $id, GoodRepository $goodRepository): Response
    {
        $good = $goodRepository->find($id);
        if (!$good) {
            throw $this->createNotFoundException('Товар не найден');
        }

        return $this->render('catalog/show.html.twig', [
            'good' => $good,
        ]);
    }

    #[Route('/cart', name: 'app_cart')]
    public function cart(SessionInterface $session, GoodRepository $goodRepository): Response
    {
        $ids   = $session->get('cart', []);
        $goods = $ids ? $goodRepository->findBy(['id' => $ids]) : [];

        return $this->render('catalog/cart.html.twig', [
            'goods' => $goods,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function cartAdd(int $id, SessionInterface $session): JsonResponse
    {
        $cart   = $session->get('cart', []);
        $inCart = in_array($id, $cart, true);

        if (!$inCart) {
            $cart[] = $id;
            $session->set('cart', $cart);
        }

        return new JsonResponse([
            'count'  => count($cart) + ($inCart ? 0 : 0), // уже обновлено выше
            'inCart' => true,
            'count'  => count($session->get('cart', [])),
        ]);
    }

    #[Route('/cart/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function cartRemove(int $id, SessionInterface $session): JsonResponse
    {
        $cart = array_filter($session->get('cart', []), fn($i) => $i !== $id);
        $cart = array_values($cart);
        $session->set('cart', $cart);

        return new JsonResponse(['count' => count($cart)]);
    }

    #[Route('/cart/count', name: 'app_cart_count')]
    public function cartCount(SessionInterface $session): JsonResponse
    {
        return new JsonResponse(['count' => count($session->get('cart', []))]);
    }
}
