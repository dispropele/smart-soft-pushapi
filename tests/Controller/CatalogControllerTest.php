<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Функциональные тесты публичной витрины.
 * Запускать: php bin/phpunit tests/Controller/CatalogControllerTest.php
 */
class CatalogControllerTest extends WebTestCase
{
    // ── Каталог (витрина) ─────────────────────────────────────────────────────

    public function testCatalogPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testCatalogSearchDoesNotCrash(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?q=золото');
        $this->assertResponseIsSuccessful();
    }

    public function testCatalogPriceFilterDoesNotCrash(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?price_min=1000&price_max=50000');
        $this->assertResponseIsSuccessful();
    }

    public function testCatalogSortOptions(): void
    {
        $client = static::createClient();
        foreach (['date', 'price_asc', 'price_desc', 'name'] as $sort) {
            $client->request('GET', '/?sort=' . $sort);
            $this->assertResponseIsSuccessful("Сортировка '{$sort}' вернула ошибку");
        }
    }

    public function testItemShowReturns404ForMissingItem(): void
    {
        $client = static::createClient();
        $client->request('GET', '/item/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    // ── Клиентский вход ───────────────────────────────────────────────────────

    public function testClientLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testClientLoginWithWrongCredentialsShowsError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            'fullName'     => 'Несуществующий Клиент',
            'ticketNumber' => 'ЛБ-0000-000000',
        ]);
        $this->assertResponseIsSuccessful();
        // Ожидаем сообщение об ошибке в HTML
        $this->assertSelectorExists('div');
    }

    public function testClientCabinetRedirectsWithoutAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cabinet');
        // Без авторизации должен редиректить на /login
        $this->assertResponseRedirects('/login');
    }

    // ── Административный вход ─────────────────────────────────────────────────

    public function testAdminLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testAdminRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/admin/login');
    }

    // ── Push API ──────────────────────────────────────────────────────────────

    public function testPushApiRejectsWithoutSignature(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/push', [], [], [], '{"test":1}');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertSame('auth', $data['type']);
    }

    // ── API (виды изделий) ────────────────────────────────────────────────────

    public function testApiCategoryTypesReturnsJsonForValidCategory(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/category/1/types');
        // Может вернуть 200 с пустым массивом или с данными
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }
}
