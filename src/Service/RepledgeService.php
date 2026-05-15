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
     * Перезалог с частичным выкупом изделий.
     *
     * @param array<int> $redeemedItemIds ID предметов, которые клиент выкупает
     */
    public function createRepledgePartial(
        LoanTicket $original,
        array $redeemedItemIds,
        string $paymentAmount,
        int $extensionDays = LoanTicket::DEFAULT_LOAN_DAYS,
        ?string $notes = null
    ): LoanTicket {
        $now = new \DateTime();

        $accruedInterest = $original->getAccruedInterest($now);
        $payment = (float) $paymentAmount;
        $allItems = $original->getPledgedItems()->toArray();
        $totalLoan = (float) $original->getLoanAmount();

        // Calculate proportional loan body per item
        $totalEstimate = array_sum(array_map(fn($i) => (float) $i->getEstimatedValue(), $allItems));
        $itemLoans = [];
        foreach ($allItems as $item) {
            $itemLoans[$item->getId()] = $totalEstimate > 0
                ? round($totalLoan * ((float) $item->getEstimatedValue() / $totalEstimate), 2)
                : 0.0;
        }

        // Sum of loan bodies for redeemed items
        $redeemedLoanSum = 0.0;
        foreach ($redeemedItemIds as $id) {
            $redeemedLoanSum += $itemLoans[(int)$id] ?? 0.0;
        }

        $minPayment = $accruedInterest + $redeemedLoanSum;
        if ($payment < $minPayment - 0.001) {
            throw new \InvalidArgumentException(sprintf(
                'Сумма платежа (%.2f ₽) меньше минимально необходимой (%.2f ₽ = проценты %.2f + выкуп %.2f).',
                $payment,
                $minPayment,
                $accruedInterest,
                $redeemedLoanSum
            ));
        }

        $extraPrincipal = max(0.0, $payment - $accruedInterest - $redeemedLoanSum);
        $newBody = round($totalLoan - $redeemedLoanSum - $extraPrincipal, 2);

        $remainingItems = array_filter($allItems, fn($i) => !in_array($i->getId(), $redeemedItemIds));

        // If all items redeemed or new body ≤ 0 — full redemption
        if ($newBody <= 0 || empty($remainingItems)) {
            $original->setStatus(LoanTicket::STATUS_CLOSED);
            $original->setClosedAt($now);
            $original->setPaidInterest((string) round($accruedInterest, 2));
            $original->setPaidPrincipal((string) round($totalLoan, 2));
            foreach ($allItems as $item) {
                $item->setStatus(PledgedItem::STATUS_REDEEMED);
                $item->setRedemptionDate($now);
            }
            $this->em->flush();
            return $original;
        }

        // Record payment on original
        $original->setPaidInterest((string) round($accruedInterest, 2));
        $original->setPaidPrincipal((string) round($redeemedLoanSum + $extraPrincipal, 2));

        // Create new ticket
        $new = new LoanTicket();
        $new->setTicketNumber($this->generateNumber());
        $new->setClient($original->getClient());
        $new->setLoanAmount((string) $newBody);
        $new->setInterestRate($original->getInterestRate());
        $new->setDailyInterestRate($original->getDailyInterestRate());
        $new->setTariff($original->getTariff());
        $new->setIssuedAt($now);
        $new->setReturnDate((clone $now)->modify("+{$extensionDays} days"));
        $new->setGraceDays($original->getGraceDays());
        $new->setStatus(LoanTicket::STATUS_OPEN);
        $new->setNotes($notes);
        $new->setRepledgedFrom($original);

        // Distribute items
        foreach ($allItems as $item) {
            if (in_array($item->getId(), $redeemedItemIds)) {
                $item->setStatus(PledgedItem::STATUS_REDEEMED);
                $item->setRedemptionDate($now);
                // Keep original ticket (historical record)
            } else {
                $item->setLoanTicket($new);
                $item->setStatus(PledgedItem::STATUS_PLEDGED);
            }
        }

        $original->setStatus(LoanTicket::STATUS_REPLEDGED);
        $original->setClosedAt($now);
        $original->setRepledgedTo($new);

        $this->em->persist($new);
        $this->em->flush();

        return $new;
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
            // Рассчитываем сумму выкупа пропорционально оценочной стоимости
            $item->setRedemptionAmount($this->calculateRedemptionAmount($ticket, $item));
        }

        $this->em->flush();
    }

    private function calculateRedemptionAmount(LoanTicket $ticket, PledgedItem $item): ?string
    {
        $totalLoan = (float) ($ticket->getLoanAmount() ?? 0);
        $totalEstimate = 0.0;
        foreach ($ticket->getPledgedItems() as $pi) {
            $totalEstimate += (float) ($pi->getEstimatedValue() ?? 0);
        }
        $itemEstimate = (float) ($item->getEstimatedValue() ?? 0);
        if ($totalEstimate <= 0) {
            return null;
        }
        $proportion = $itemEstimate / $totalEstimate;
        $amount = round($totalLoan * $proportion, 2);
        return number_format($amount, 2, '.', '');
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
