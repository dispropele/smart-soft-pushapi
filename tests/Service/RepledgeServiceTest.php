<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\LoanTicket;
use App\Entity\PledgedItem;
use App\Service\RepledgeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Тесты RepledgeService: перезалог, выкуп, реализация.
 */
class RepledgeServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private RepledgeService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new RepledgeService($this->em);
    }

    private function makeTicket(
        float $amount    = 10000.0,
        float $dailyRate = 0.3,
        int   $daysAgo   = 30,
        int   $graceDays = 30
    ): LoanTicket {
        $client = new Client();
        $client->setFullName('Иван Иванов');
        $client->setPassportNumber('123456');

        $ticket = new LoanTicket();
        $ticket->setClient($client);
        $ticket->setLoanAmount((string) $amount);
        $ticket->setDailyInterestRate((string) $dailyRate);
        $ticket->setInterestRate((string) round($dailyRate * 30, 2));
        $ticket->setIssuedAt((new \DateTime())->modify("-{$daysAgo} days"));
        $ticket->setReturnDate((new \DateTime())->modify('+1 day'));
        $ticket->setGraceDays($graceDays);
        $ticket->setStatus(LoanTicket::STATUS_OPEN);
        $ticket->setTicketNumber('ЛБ-TEST-001');

        return $ticket;
    }

    private function addItem(LoanTicket $ticket, string $name = 'Кольцо'): PledgedItem
    {
        $item = new PledgedItem();
        $item->setName($name);
        $item->setStatus(PledgedItem::STATUS_PLEDGED);
        $ticket->addPledgedItem($item);
        return $item;
    }

    // ── Перезалог ─────────────────────────────────────────────────────────────

    public function testCreateRepledgeCreatesNewTicket(): void
    {
        $original = $this->makeTicket(amount: 10000.0, dailyRate: 0.3, daysAgo: 30);
        $item      = $this->addItem($original);

        $accrued  = $original->getAccruedInterest(); // 10000 * 0.3% * 30 = 900
        $new      = $this->service->createRepledge($original, paymentAmount: (string) $accrued);

        // Старый билет закрыт как перезалог
        $this->assertSame(LoanTicket::STATUS_REPLEDGED, $original->getStatus());
        $this->assertNotNull($original->getClosedAt());
        $this->assertSame($new, $original->getRepledgedTo());

        // Новый билет открыт
        $this->assertSame(LoanTicket::STATUS_OPEN, $new->getStatus());
        $this->assertSame($original->getClient(), $new->getClient());

        // Предмет перенесён на новый билет
        $this->assertSame($new, $item->getLoanTicket());
        $this->assertSame(PledgedItem::STATUS_PLEDGED, $item->getStatus());
    }

    public function testCreateRepledgeReducesPrincipalWhenPaymentExceedsInterest(): void
    {
        // Долг 10000, проценты за 30 дней = 900, платим 1900 → гасим 900 % + 1000 тело
        $original = $this->makeTicket(amount: 10000.0, dailyRate: 0.3, daysAgo: 30);
        $this->addItem($original);

        $new = $this->service->createRepledge($original, paymentAmount: '1900');

        $this->assertEqualsWithDelta(9000.0, (float) $new->getLoanAmount(), 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $original->getPaidInterest(), 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $original->getPaidPrincipal(), 0.01);
    }

    public function testCreateRepledgeRedeemsWhenFullyPaid(): void
    {
        $original = $this->makeTicket(amount: 1000.0, dailyRate: 0.3, daysAgo: 10);
        $item = $this->addItem($original);
        $totalDebt = $original->getTotalDebt();

        $result = $this->service->createRepledge($original, paymentAmount: (string) $totalDebt);

        $this->assertSame($original, $result);
        $this->assertSame(LoanTicket::STATUS_CLOSED, $result->getStatus());
        $this->assertGreaterThan(0, (float) $result->getPaidInterest());
        $this->assertGreaterThan(0, (float) $result->getPaidPrincipal());
        $this->assertSame(PledgedItem::STATUS_REDEEMED, $item->getStatus());
    }

    public function testCreateRepledgeThrowsWhenPaymentExceedsDebt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $original = $this->makeTicket(amount: 1000.0, dailyRate: 0.3, daysAgo: 10);
        $this->addItem($original);
        $this->service->createRepledge($original, paymentAmount: '999999');
    }

    public function testRepledgeInheritsInterestRate(): void
    {
        $original = $this->makeTicket(amount: 5000.0, dailyRate: 0.3, daysAgo: 10);
        $this->addItem($original);

        $new = $this->service->createRepledge($original, paymentAmount: '150');

        $this->assertSame($original->getInterestRate(), $new->getInterestRate());
        $this->assertSame($original->getDailyInterestRate(), $new->getDailyInterestRate());
    }

    public function testRepledgeExtensionDaysAffectsReturnDate(): void
    {
        $original = $this->makeTicket(amount: 5000.0, dailyRate: 0.3, daysAgo: 10);
        $this->addItem($original);

        $new = $this->service->createRepledge($original, paymentAmount: '150', extensionDays: 60);

        $expected = (new \DateTime())->modify('+60 days');
        $diff     = abs($new->getReturnDate()->getTimestamp() - $expected->getTimestamp());
        $this->assertLessThan(60, $diff, 'Дата возврата должна быть ~60 дней от сегодня');
    }

    // ── Выкуп ─────────────────────────────────────────────────────────────────

    public function testRedeemClosesTicketAndMarksItemsRedeemed(): void
    {
        $ticket = $this->makeTicket();
        $item1  = $this->addItem($ticket, 'Кольцо');
        $item2  = $this->addItem($ticket, 'Цепочка');

        $this->service->redeem($ticket);

        $this->assertSame(LoanTicket::STATUS_CLOSED, $ticket->getStatus());
        $this->assertNotNull($ticket->getClosedAt());
        $this->assertSame(PledgedItem::STATUS_REDEEMED, $item1->getStatus());
        $this->assertSame(PledgedItem::STATUS_REDEEMED, $item2->getStatus());
        $this->assertNotNull($item1->getRedemptionDate());
    }

    public function testRedeemWithNoItemsDoesNotFail(): void
    {
        $ticket = $this->makeTicket();
        $this->service->redeem($ticket);
        $this->assertSame(LoanTicket::STATUS_CLOSED, $ticket->getStatus());
    }

    // ── Реализация ────────────────────────────────────────────────────────────

    public function testMoveToSaleMarksItemsForSale(): void
    {
        $ticket = $this->makeTicket();
        $item   = $this->addItem($ticket);

        $this->service->moveToSale($ticket);

        $this->assertSame(LoanTicket::STATUS_EXPIRED, $ticket->getStatus());
        $this->assertSame(PledgedItem::STATUS_FOR_SALE, $item->getStatus());
        $this->assertNotNull($item->getPublishedAt());
    }

    // ── activateGrace ─────────────────────────────────────────────────────────

    public function testActivateGraceSetsGraceStatusWhenExpired(): void
    {
        $ticket = $this->makeTicket(daysAgo: 35); // вышел за 30-дневный срок
        // return date в прошлом
        $ticket->setReturnDate((new \DateTime())->modify('-5 days'));

        $this->service->activateGrace($ticket);

        $this->assertSame(LoanTicket::STATUS_GRACE, $ticket->getStatus());
    }

    public function testActivateGraceDoesNothingIfNotExpiredYet(): void
    {
        $ticket = $this->makeTicket(daysAgo: 10);
        // return date в будущем
        $ticket->setReturnDate((new \DateTime())->modify('+20 days'));

        $this->service->activateGrace($ticket);

        $this->assertSame(LoanTicket::STATUS_OPEN, $ticket->getStatus());
    }
}
