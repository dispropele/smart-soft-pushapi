<?php

namespace App\Controller;

use App\Entity\PledgedItem;
use App\Entity\SaleRequest;
use App\Repository\ClientRepository;
use App\Repository\PledgedItemRepository;
use App\Service\SystemLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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

    #[Route('/item/{id}/request', name: 'app_item_purchase_request', methods: ['GET', 'POST'])]
    public function requestPurchase(
        int $id,
        Request $request,
        SessionInterface $session,
        PledgedItemRepository $repo,
        ClientRepository $clientRepo,
        EntityManagerInterface $em,
        SystemLogger $logger
    ): Response {
        $item = $repo->find($id);
        if (!$item || !$item->isForSale()) {
            throw $this->createNotFoundException('Товар не найден');
        }

        $error = null;
        $success = false;
        $client = null;
        if ($session->get('client_id')) {
            $client = $clientRepo->find((int) $session->get('client_id'));
        }

        $data = [
            'fullName' => $client?->getFullName() ?? '',
            'phone'    => $client?->getPhone() ?? '',
            'email'    => $client?->getEmail() ?? '',
            'message'  => '',
            'consent'  => false,
        ];

        if ($request->isMethod('POST')) {
            $data['fullName'] = trim((string) $request->request->get('fullName', ''));
            $data['phone']    = trim((string) $request->request->get('phone', ''));
            $data['email']    = trim((string) $request->request->get('email', ''));
            $data['message']  = trim((string) $request->request->get('message', ''));
            $data['consent']  = $request->request->get('consent') !== null;

            if ($data['fullName'] === '' || $data['phone'] === '') {
                $error = 'Пожалуйста, укажите ФИО и контактный телефон.';
            } elseif ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Пожалуйста, укажите корректный email.';
            } elseif (!$data['consent']) {
                $error = 'Для отправки заявки необходимо согласие на обработку персональных данных.';
            } else {
                $saleRequest = new SaleRequest();
                $saleRequest->setPledgedItem($item)
                    ->setFullName($data['fullName'])
                    ->setPhone($data['phone'])
                    ->setEmail($data['email'] ?: null)
                    ->setMessage($data['message'] ?: null);

                $em->persist($saleRequest);
                $em->flush();

                $logger->info(
                    \App\Entity\SystemLog::CHANNEL_SALE,
                    'Поступила заявка на покупку изделия',
                    [
                        'itemId'        => $item->getId(),
                        'itemName'      => $item->getName(),
                        'saleRequestId' => $saleRequest->getId(),
                        'fullName'      => $data['fullName'],
                        'phone'         => $data['phone'],
                        'email'         => $data['email'],
                        'message'       => $data['message'],
                    ],
                    $item->getId()
                );

                $this->addFlash('success', 'Ваша заявка на покупку отправлена. С вами свяжутся в ближайшее время.');
                return $this->redirectToRoute('app_item_show', ['id' => $item->getId()]);
            }
        }

        return $this->render('catalog/purchase_request.html.twig', [
            'item'    => $item,
            'error'   => $error,
            'data'    => $data,
        ]);
    }
}
