<?php

namespace App\Tests\Entity;

use App\Entity\LoanTicket;
use App\Entity\Tariff;
use PHPUnit\Framework\TestCase;

/**
 * Тесты Tariff и интеграции тарифа в LoanTicket.
 */
class TariffTest extends TestCase
{
    public function testTariffToString(): void
    {
        $tariff = new Tariff();
        $tariff->setName('Стандарт');
        $tariff->setDailyRate('0.3000');

        $this->assertSame('Стандарт · 0,3%/день', (string) $tariff);
        $this->assertSame('9', $tariff->getMonthlyRate());
    }

    public function testTariffIsActiveByDefault(): void
    {
        $tariff = new Tariff();
        $this->assertTrue($tariff->isActive());
    }

    public function testTariffCanBeDeactivated(): void
    {
        $tariff = new Tariff();
        $tariff->setIsActive(false);
        $this->assertFalse($tariff->isActive());
    }

    // ── Логика applyTariff из контроллера (воспроизведена здесь) ─────────────

    /**
     * Воспроизводим приватный метод applyTariff из LoanTicketCrudController,
     * чтобы убедиться в корректности расчёта ставок.
     */
    private function applyTariff(LoanTicket $ticket): void
    {
        $tariff = $ticket->getTariff();
        if ($tariff === null) {
            return;
        }

        $ticket->setDailyInterestRate($tariff->getDailyRate());
        $ticket->setInterestRate($tariff->getMonthlyRate());
    }

    public function testApplyTariffSetsDailyRateOnTicket(): void
    {
        $tariff = new Tariff();
        $tariff->setName('Тест')->setDailyRate('0.3000');

        $ticket = new LoanTicket();
        $ticket->setTariff($tariff);
        $ticket->setLoanAmount('10000');

        $this->applyTariff($ticket);

        $this->assertSame('0.3000', $ticket->getDailyInterestRate());
    }

    public function testApplyTariffCalculatesMonthlyRateAutomatically(): void
    {
        $tariff = new Tariff();
        $tariff->setName('Тест')->setDailyRate('0.3000');

        $ticket = new LoanTicket();
        $ticket->setTariff($tariff);
        $ticket->setLoanAmount('10000');
        // interestRate пуста

        $this->applyTariff($ticket);

        // 0.3 * 30 = 9.0
        $this->assertEqualsWithDelta(9.0, (float) $ticket->getInterestRate(), 0.001);
    }

    public function testApplyTariffAlwaysSyncsMonthlyRateFromTariff(): void
    {
        $tariff = new Tariff();
        $tariff->setName('Тест')->setDailyRate('0.3000');

        $ticket = new LoanTicket();
        $ticket->setTariff($tariff);
        $ticket->setLoanAmount('10000');
        $ticket->setInterestRate('12.5');

        $this->applyTariff($ticket);

        $this->assertEqualsWithDelta(9.0, (float) $ticket->getInterestRate(), 0.001);
        $this->assertSame('0.3000', $ticket->getDailyInterestRate());
    }

    public function testApplyTariffDoesNothingWhenNoTariff(): void
    {
        $ticket = new LoanTicket();
        $ticket->setLoanAmount('10000');
        // tariff = null

        $this->applyTariff($ticket);

        $this->assertNull($ticket->getDailyInterestRate());
        $this->assertNull($ticket->getInterestRate());
    }

    public function testTariffAccruedInterestCalculation(): void
    {
        // 10000 руб * 0.3% в день * 30 дней = 900 руб
        $tariff = new Tariff();
        $tariff->setDailyRate('0.3');

        $ticket = new LoanTicket();
        $ticket->setTariff($tariff);
        $ticket->setLoanAmount('10000');
        $ticket->setDailyInterestRate('0.3');
        $ticket->setIssuedAt((new \DateTime())->modify('-30 days'));

        $accrued = $ticket->getAccruedInterest();
        $this->assertEqualsWithDelta(900.0, $accrued, 1.0);
    }
}
