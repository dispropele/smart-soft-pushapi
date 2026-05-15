<?php

namespace App\Controller\Admin;

use App\Entity\PledgedItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/reports/sold-items', name: 'report_sold_items')]
class ReportController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(Request $request): Response
    {
        $dateFrom = $request->query->get('date_from')
            ? new \DateTime($request->query->get('date_from'))
            : (new \DateTime())->modify('-30 days');
        $dateTo = $request->query->get('date_to')
            ? new \DateTime($request->query->get('date_to') . ' 23:59:59')
            : new \DateTime('23:59:59');

        $items = $this->em->createQueryBuilder()
            ->select('p, c, gt, ms')
            ->from(PledgedItem::class, 'p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.goodType', 'gt')
            ->leftJoin('p.metalStandard', 'ms')
            ->where('p.status = :sold')
            ->andWhere('p.statusDate BETWEEN :from AND :to')
            ->setParameter('sold', PledgedItem::STATUS_SOLD)
            ->setParameter('from', $dateFrom)
            ->setParameter('to', $dateTo)
            ->orderBy('p.statusDate', 'DESC')
            ->getQuery()
            ->getResult();

        $total = array_reduce($items, fn ($carry, $item) => $carry + (float) $item->getSoldPrice(), 0.0);

        return $this->render('admin/reports/sold_items.html.twig', [
            'items' => $items,
            'total' => $total,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
