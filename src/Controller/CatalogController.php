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
        );

        $total = $goodRepository->countForCatalog($search);

        return $this->render('catalog/index.html.twig', [
            'goods'   => $goods,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int) ceil($total / $perPage),
            'perPage' => $perPage,
            'search'  => $search,
            'sort'    => $sort,
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
