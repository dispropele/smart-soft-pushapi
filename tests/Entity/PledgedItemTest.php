<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\GoodType;
use App\Entity\Insert;
use App\Entity\LoanTicket;
use App\Entity\MetalColor;
use App\Entity\MetalStandard;
use App\Entity\PledgedItem;
use App\Entity\PledgedItemImage;
use PHPUnit\Framework\TestCase;

/**
 * Тесты сущности PledgedItem: статусы, изображения, связи.
 */
class PledgedItemTest extends TestCase
{
    private function makeItem(string $status = PledgedItem::STATUS_PLEDGED): PledgedItem
    {
        $item = new PledgedItem();
        $item->setName('Тестовое изделие');
        $item->setStatus($status);
        return $item;
    }

    // ── Статусы ───────────────────────────────────────────────────────────────

    public function testIsPledgedWhenStatusIsPledged(): void
    {
        $item = $this->makeItem(PledgedItem::STATUS_PLEDGED);
        $this->assertTrue($item->isPledged());
        $this->assertFalse($item->isForSale());
        $this->assertFalse($item->isSold());
    }

    public function testIsForSaleWhenStatusIsForSale(): void
    {
        $item = $this->makeItem(PledgedItem::STATUS_FOR_SALE);
        $this->assertTrue($item->isForSale());
        $this->assertTrue($item->isOnCatalog());
    }

    public function testIsRedeemedWhenStatusIsRedeemed(): void
    {
        $item = $this->makeItem(PledgedItem::STATUS_REDEEMED);
        $this->assertTrue($item->isRedeemed());
    }

    public function testIsSoldWhenStatusIsSold(): void
    {
        $item = $this->makeItem(PledgedItem::STATUS_SOLD);
        $this->assertTrue($item->isSold());
        $this->assertFalse($item->isOnCatalog());
    }

    public function testSetStatusIgnoresNull(): void
    {
        $item = $this->makeItem();
        $item->setStatus(null);
        $this->assertSame(PledgedItem::STATUS_PLEDGED, $item->getStatus());
    }

    public function testGetStatusLabel(): void
    {
        $labels = [
            PledgedItem::STATUS_PLEDGED   => 'На хранении',
            PledgedItem::STATUS_REDEEMED  => 'Выкуплен',
            PledgedItem::STATUS_FOR_SALE  => 'На реализации',
            PledgedItem::STATUS_SOLD      => 'Продан',
            PledgedItem::STATUS_WITHDRAWN => 'Изъят',
            PledgedItem::STATUS_HIDDEN    => 'Скрыт',
        ];

        foreach ($labels as $status => $expected) {
            $item = $this->makeItem($status);
            $this->assertSame($expected, $item->getStatusLabel(), "Статус: $status");
        }
    }

    // ── Изображения ───────────────────────────────────────────────────────────

    public function testAddImage(): void
    {
        $item  = $this->makeItem();
        $image = new PledgedItemImage();
        $image->setSrc('/uploads/test.jpg');
        $image->setPreview('/uploads/test_thumb.jpg');
        $image->setIsCover(true);

        $item->addImage($image);

        $this->assertCount(1, $item->getImages());
        $this->assertSame($item, $image->getPledgedItem());
    }

    public function testAddImageIsIdempotent(): void
    {
        $item  = $this->makeItem();
        $image = new PledgedItemImage();
        $image->setSrc('/uploads/test.jpg')->setPreview('/uploads/t.jpg');

        $item->addImage($image);
        $item->addImage($image); // второй раз — не должен дублировать

        $this->assertCount(1, $item->getImages());
    }

    // ── Связи ─────────────────────────────────────────────────────────────────

    public function testSetAndGetCategory(): void
    {
        $cat  = new Category();
        $cat->setName('Кольца');
        $item = $this->makeItem();
        $item->setCategory($cat);
        $this->assertSame($cat, $item->getCategory());
    }

    public function testSetAndGetMetalStandard(): void
    {
        $ms = new MetalStandard();
        $ms->setName('585');

        $item = $this->makeItem();
        $item->setMetalStandard($ms);
        $this->assertSame($ms, $item->getMetalStandard());
    }

    public function testSetAndGetLoanTicket(): void
    {
        $ticket = new LoanTicket();
        $ticket->setTicketNumber('ЛБ-TEST');
        $item = $this->makeItem();
        $item->setLoanTicket($ticket);
        $this->assertSame($ticket, $item->getLoanTicket());
    }

    public function testLoanTicketCanBeNull(): void
    {
        $item = $this->makeItem();
        $item->setLoanTicket(null);
        $this->assertNull($item->getLoanTicket());
    }

    // ── Числовые поля ─────────────────────────────────────────────────────────

    public function testWeightAndPriceSettersGetters(): void
    {
        $item = $this->makeItem();
        $item->setItemWeight('5.25');
        $item->setScrapWeight('4.90');
        $item->setSoldPrice('45000.00');
        $item->setEstimatedValue('40000.00');

        $this->assertSame('5.25', $item->getItemWeight());
        $this->assertSame('4.90', $item->getScrapWeight());
        $this->assertSame('45000.00', $item->getSoldPrice());
        $this->assertSame('40000.00', $item->getEstimatedValue());
    }

    public function testInsertWeightAndDescription(): void
    {
        $item = $this->makeItem();
        $item->setInsertWeight('0.25');
        $item->setInsertDescription('Бриллиант круглый');

        $this->assertSame('0.25', $item->getInsertWeight());
        $this->assertSame('Бриллиант круглый', $item->getInsertDescription());
    }

    // ── toString ─────────────────────────────────────────────────────────────

    public function testToStringReturnsName(): void
    {
        $item = $this->makeItem();
        $this->assertSame('Тестовое изделие', (string) $item);
    }
}
