<?php
namespace App\Service;

use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use Doctrine\ORM\EntityManagerInterface;

class RepledgeService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Перезалог с частичной оплатой процентов и/или тела займа.
     *
     * @param string|null $paymentAmount  Внесённая клиентом сумма (проценты + опционально часть тела)
     * @param string|null $newLoanAmount  Новая сумма займа (если null — считается автоматически)
     */
    public function createRepledge(
        LoanTicket $original,
        ?string    $paymentAmount  = null,
        ?string    $newInterestRate = null,
        int        $extensionDays  = LoanTicket::DEFAULT_LOAN_DAYS,
        ?string    $notes          = null,
        ?string    $newLoanAmount  = null
    ): LoanTicket {
        $now = new \DateTime();

        // 1. Считаем накопившиеся проценты
        $accruedInterest = $original->getAccruedInterest($now);
        $payment         = $paymentAmount !== null ? (float)$paymentAmount : $accruedInterest;

        // 2. Сначала гасим проценты, остаток идёт на уменьшение тела
        $interestPaid   = min($payment, $accruedInterest);
        $principalPaid  = max(0.0, $payment - $interestPaid);

        // 3. Тело нового займа
        $originalBody = (float)$original->getLoanAmount();
        $newBody      = $newLoanAmount !== null
            ? (float)$newLoanAmount
            : round($originalBody - $principalPaid, 2);

        if ($newBody <= 0) {
            // Если тело займа полностью погашено — просто выкупаем
            $this->redeem($original);
            throw new \LogicException('Сумма платежа погашает весь долг — используйте «Выкуп».');
        }

        // 4. Фиксируем оплаты на старом билете
        $original->setPaidInterest((string)round($interestPaid, 2));
        $original->setPaidPrincipal((string)round($principalPaid, 2));

        // 5. Создаём новый билет
        $new = new LoanTicket();
        $new->setTicketNumber($this->generateNumber());
        $new->setClient($original->getClient());
        $new->setLoanAmount((string)$newBody);
        $new->setInterestRate($newInterestRate ?? $original->getInterestRate());
        $new->setDailyInterestRate($original->getDailyInterestRate());
        $new->setTariff($original->getTariff());
        $new->setIssuedAt($now);
        $new->setReturnDate((clone $now)->modify("+{$extensionDays} days"));
        $new->setGraceDays($original->getGraceDays());
        $new->setStatus(LoanTicket::STATUS_OPEN);
        $new->setNotes($notes);
        $new->setRepledgedFrom($original);

        // 6. Переносим предметы
        foreach ($original->getPledgedItems() as $item) {
            $item->setLoanTicket($new);
            $item->setStatus(PledgedItem::STATUS_PLEDGED);
        }

        // 7. Закрываем старый билет
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