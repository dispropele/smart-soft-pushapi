<?php

namespace App\Tests\Entity;

use App\Entity\Client;
use App\Entity\LoanTicket;
use App\Entity\Tariff;
use PHPUnit\Framework\TestCase;

/**
 * Тесты бизнес-логики сущности LoanTicket:
 * расчёт процентов, статусы, перезалог.
 */
class LoanTicketTest extends TestCase
{
    private function makeTicket(
        float $amount = 10000.0,
        float $dailyRate = 0.3,
        int   $daysAgo = 30,
        int   $termDays = 1
    ): LoanTicket {
        $ticket = new LoanTicket();
        $issuedAt = (new \DateTime())->modify("-{$daysAgo} days");
        $ticket->setLoanAmount((string) $amount);
        $ticket->setDailyInterestRate((string) $dailyRate);
        $ticket->setInterestRate((string) round($dailyRate * 30, 2));
        $ticket->setIssuedAt($issuedAt);
        $ticket->setReturnDate((clone $issuedAt)->modify("+{$termDays} days"));
        $ticket->setStatus(LoanTicket::STATUS_OPEN);
        return $ticket;
    }

    // ── Расчёт процентов ──────────────────────────────────────────────────────

    public function testGetAccruedInterestBasic(): void
    {
        $ticket = $this->makeTicket(amount: 10000.0, dailyRate: 0.3, daysAgo: 10);

        // 10 000 * 0.3% * 10 дней = 300
        $this->assertEqualsWithDelta(300.0, $ticket->getAccruedInterest(), 0.01);
    }

    public function testGetAccruedInterestMinimumOneDay(): void
    {
        // Выданный только что — минимум 1 день
        $ticket = $this->makeTicket(amount: 10000.0, dailyRate: 0.3, daysAgo: 0);

        // 10 000 * 0.3% * 1 день = 30
        $this->assertEqualsWithDelta(30.0, $ticket->getAccruedInterest(), 0.01);
    }

    public function testGetAccruedInterestZeroRateReturnsZero(): void
    {
        $ticket = $this->makeTicket(amount: 10000.0, dailyRate: 0.0, daysAgo: 30);
        $this->assertEqualsWithDelta(0.0, $ticket->getAccruedInterest(), 0.001);
    }

    public function testGetElapsedDaysIsAtLeastOne(): void
    {
        $ticket = $this->makeTicket(daysAgo: 0);
        $this->assertSame(1, $ticket->getElapsedDays());
    }

    public function testGetElapsedDays(): void
    {
        $ticket = $this->makeTicket(daysAgo: 15);
        $this->assertSame(15, $ticket->getElapsedDays());
    }

    // ── Расчёт сумм к возврату ────────────────────────────────────────────────

    public function testGetReturnAmount(): void
    {
        $ticket = $this->makeTicket(amount: 10000.0, dailyRate: 0.3, daysAgo: 30, termDays: 30);
        // 30 дней по ставке 0.3% в день = 9%
        // returnAmount = 10000 * (1 + 9/100) = 10900
        $this->assertEqualsWithDelta(10900.0, (float) $ticket->getReturnAmount(), 0.01);
    }

    public function testGetReturnAmountWithZeroRate(): void
    {
        $ticket = $this->makeTicket(amount: 5000.0, dailyRate: 0.0, daysAgo: 30, termDays: 30);
        $this->assertEqualsWithDelta(5000.0, (float) $ticket->getReturnAmount(), 0.01);
    }

    // ── Льготный период ───────────────────────────────────────────────────────

    public function testGetGraceEndDate(): void
    {
        $returnDate = new \DateTime('2026-06-01');
        $ticket     = new LoanTicket();
        $ticket->setReturnDate($returnDate);
        $ticket->setGraceDays(30);

        $expected = new \DateTime('2026-07-01');
        $this->assertEquals($expected->format('Y-m-d'), $ticket->getGraceEndDate()->format('Y-m-d'));
    }

    public function testGetGraceEndDateIsNullWhenNoReturnDate(): void
    {
        $ticket = new LoanTicket();
        // Конструктор выставляет returnDate по умолчанию — сбрасываем для проверки guard-ветки
        $ref = new \ReflectionProperty(LoanTicket::class, 'returnDate');
        $ref->setValue($ticket, null);

        $this->assertNull($ticket->getGraceEndDate());
    }

    // ── Статусы ───────────────────────────────────────────────────────────────

    public function testIsActiveReturnsTrueForOpenAndGrace(): void
    {
        $open  = $this->makeTicket();
        $grace = $this->makeTicket();
        $grace->setStatus(LoanTicket::STATUS_GRACE);

        $this->assertTrue($open->isActive());
        $this->assertTrue($grace->isActive());
    }

    public function testIsActiveReturnsFalseForClosed(): void
    {
        $ticket = $this->makeTicket();
        $ticket->setStatus(LoanTicket::STATUS_CLOSED);
        $this->assertFalse($ticket->isActive());
    }

    public function testStatusChoicesContainsAllStatuses(): void
    {
        $choices = LoanTicket::statusChoices();
        $this->assertContains(LoanTicket::STATUS_OPEN, $choices);
        $this->assertContains(LoanTicket::STATUS_GRACE, $choices);
        $this->assertContains(LoanTicket::STATUS_CLOSED, $choices);
        $this->assertContains(LoanTicket::STATUS_EXPIRED, $choices);
        $this->assertContains(LoanTicket::STATUS_REPLEDGED, $choices);
        $this->assertContains(LoanTicket::STATUS_CANCELLED, $choices);
    }

    public function testSetStatusIgnoresNull(): void
    {
        $ticket = $this->makeTicket();
        $ticket->setStatus(null);
        // setStatus защищён от null — статус не должен поменяться
        $this->assertSame(LoanTicket::STATUS_OPEN, $ticket->getStatus());
    }

    // ── Тариф ─────────────────────────────────────────────────────────────────

    public function testTariffCanBeSetAndRead(): void
    {
        $tariff = new Tariff();
        $tariff->setName('Стандарт')->setDailyRate('0.3000');

        $ticket = $this->makeTicket();
        $ticket->setTariff($tariff);

        $this->assertSame($tariff, $ticket->getTariff());
        $this->assertSame('0.3000', $tariff->getDailyRate());
    }

    // ── Предметы залога ───────────────────────────────────────────────────────

    public function testAddAndRemovePledgedItem(): void
    {
        $ticket = $this->makeTicket();
        $item   = new \App\Entity\PledgedItem();
        $item->setName('Кольцо тестовое');

        $ticket->addPledgedItem($item);
        $this->assertCount(1, $ticket->getPledgedItems());
        $this->assertSame($ticket, $item->getLoanTicket());

        $ticket->removePledgedItem($item);
        $this->assertCount(0, $ticket->getPledgedItems());
    }

    // ── Валидация дат ─────────────────────────────────────────────────────────

    public function testToStringReturnsTicketNumber(): void
    {
        $ticket = new LoanTicket();
        $ticket->setTicketNumber('ЛБ-2026-TEST');
        $this->assertSame('ЛБ-2026-TEST', (string) $ticket);
    }
}
