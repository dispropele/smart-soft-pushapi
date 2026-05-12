<?php
namespace App\Service;

use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use Doctrine\ORM\EntityManagerInterface;

class RepledgeService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Создаёт новый залоговый билет на основе старого (перезалог).
     * Старый билет получает статус repledged.
     * Предметы переносятся на новый билет.
     */
    public function createRepledge(
        LoanTicket $original,
        string     $newLoanAmount,
        ?string    $newInterestRate = null,
        int        $extensionDays   = LoanTicket::DEFAULT_LOAN_DAYS,
        ?string    $notes           = null
    ): LoanTicket {
        $new = new LoanTicket();
        $new->setTicketNumber($this->generateNumber());
        $new->setClient($original->getClient());
        $new->setLoanAmount($newLoanAmount);
        $new->setInterestRate($newInterestRate ?? $original->getInterestRate());
        $new->setIssuedAt(new \DateTime());
        $new->setReturnDate((new \DateTime())->modify("+{$extensionDays} days"));
        $new->setGraceDays($original->getGraceDays());
        $new->setStatus(LoanTicket::STATUS_OPEN);
        $new->setNotes($notes);
        $new->setRepledgedFrom($original);

        // Перенести предметы
        foreach ($original->getPledgedItems() as $item) {
            $item->setLoanTicket($new);
            $item->setStatus(PledgedItem::STATUS_PLEDGED);
        }

        $original->setStatus(LoanTicket::STATUS_REPLEDGED);
        $original->setClosedAt(new \DateTime());
        $original->setRepledgedTo($new);

        $this->em->persist($new);
        $this->em->flush();

        return $new;
    }

    /**
     * Выкуп залога клиентом.
     */
    public function redeem(LoanTicket $ticket): void
    {
        $ticket->setStatus(LoanTicket::STATUS_CLOSED);
        $ticket->setClosedAt(new \DateTime());

        foreach ($ticket->getPledgedItems() as $item) {
            $item->setStatus(PledgedItem::STATUS_REDEEMED);
            $item->setRedemptionDate(new \DateTime());
        }

        $this->em->flush();
    }

    /**
     * Перевод предметов на реализацию (ломбард забирает по истечении grace).
     */
    public function moveToSale(LoanTicket $ticket): void
    {
        $ticket->setStatus(LoanTicket::STATUS_EXPIRED);
        $ticket->setClosedAt(new \DateTime());

        foreach ($ticket->getPledgedItems() as $item) {
            $item->setStatus(PledgedItem::STATUS_FOR_SALE);
            $item->setPublishedAt(new \DateTime());
        }

        $this->em->flush();
    }

    /**
     * Активация льготного периода (вызывается планировщиком или вручную).
     */
    public function activateGrace(LoanTicket $ticket): void
    {
        if ($ticket->isOpen() && $ticket->getDaysLeft() <= 0) {
            $ticket->setStatus(LoanTicket::STATUS_GRACE);
            $this->em->flush();
        }
    }

    private function generateNumber(): string
    {
        return 'ЛБ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}