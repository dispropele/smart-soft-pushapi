<?php

namespace App\Service;

use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use Doctrine\ORM\EntityManagerInterface;

class RepledgeService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Перезалог или полный выкуп (если платёж покрывает весь долг).
     *
     * @return LoanTicket закрытый билет при выкупе или новый билет при перезалоге
     */
    public function createRepledge(
        LoanTicket $original,
        ?string $paymentAmount = null,
        ?string $newInterestRate = null,
        int $extensionDays = LoanTicket::DEFAULT_LOAN_DAYS,
        ?string $notes = null,
        ?string $newLoanAmount = null
    ): LoanTicket {
        $now = new \DateTime();

        $accruedInterest = $original->getAccruedInterest($now);
        $payment = $paymentAmount !== null ? (float) $paymentAmount : $accruedInterest;
        $totalDebt = $original->getTotalDebt($now);

        if ($payment > $totalDebt + 0.001) {
            throw new \InvalidArgumentException(sprintf(
                'Сумма платежа (%.2f ₽) превышает долг по билету (%.2f ₽: тело займа + проценты).',
                $payment,
                $totalDebt
            ));
        }

        if ($payment >= $totalDebt - 0.001) {
            $this->applyPayment($original, $payment, $now);
            $this->redeem($original);

            return $original;
        }

        $interestPaid = min($payment, $accruedInterest);
        $principalPaid = max(0.0, $payment - $interestPaid);

        $originalBody = (float) $original->getLoanAmount();
        $newBody = $newLoanAmount !== null
            ? (float) $newLoanAmount
            : round($originalBody - $principalPaid, 2);

        if ($newBody <= 0) {
            $this->applyPayment($original, $payment, $now);
            $this->redeem($original);

            return $original;
        }

        $original->setPaidInterest((string) round($interestPaid, 2));
        $original->setPaidPrincipal((string) round($principalPaid, 2));

        $new = new LoanTicket();
        $new->setTicketNumber($this->generateNumber());
        $new->setClient($original->getClient());
        $new->setLoanAmount((string) $newBody);
        $new->setInterestRate($newInterestRate ?? $original->getInterestRate());
        $new->setDailyInterestRate($original->getDailyInterestRate());
        $new->setTariff($original->getTariff());
        $new->setIssuedAt($now);
        $new->setReturnDate((clone $now)->modify("+{$extensionDays} days"));
        $new->setGraceDays($original->getGraceDays());
        $new->setStatus(LoanTicket::STATUS_OPEN);
        $new->setNotes($notes);
        $new->setRepledgedFrom($original);

        foreach ($original->getPledgedItems() as $item) {
            $item->setLoanTicket($new);
            $item->setStatus(PledgedItem::STATUS_PLEDGED);
        }

        $original->setStatus(LoanTicket::STATUS_REPLEDGED);
        $original->setClosedAt($now);
        $original->setRepledgedTo($new);

        $this->em->persist($new);
        $this->em->flush();

        return $new;
    }

    /**
     * Выкуп залога клиентом.
     */
    public function redeem(LoanTicket $ticket, ?string $paymentAmount = null): void
    {
        if ($paymentAmount !== null) {
            $this->applyPayment($ticket, (float) $paymentAmount, new \DateTime());
        }

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

    private function applyPayment(LoanTicket $ticket, float $payment, \DateTimeInterface $at): void
    {
        $accrued = $ticket->getAccruedInterest($at);
        $interestPaid = min($payment, $accrued);
        $principalPaid = max(0.0, $payment - $interestPaid);

        $ticket->setPaidInterest((string) round($interestPaid, 2));
        $ticket->setPaidPrincipal((string) round($principalPaid, 2));
    }

    private function generateNumber(): string
    {
        return 'ЛБ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
