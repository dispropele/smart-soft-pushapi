# 🏦 Комиссионка - Витрина товаров

Витрина комиссионки с автоматической синхронизацией товаров через **Push API СмартЛомбард**.

---

## 📸 Скриншоты

### Витрина товаров
![Каталог](docs/screenshots/catalog.png)

### Карточка товара
![Карточка товара](docs/screenshots/product.png)

### Корзина
![Корзина](docs/screenshots/cart.png)

### Административная панель
![Админка](docs/screenshots/admin.png)

---

## ✨ Возможности

- **Публичная витрина** — каталог товаров с поиском по названию, филиалу и городу
- **Пагинация** — по 20 товаров на странице
- **Карточка товара** — фото, характеристики, металл/проба, информация о филиале
- **Корзина** — сессионная, без регистрации, живой счётчик в шапке
- **Push API** — webhook для приёма данных от СмартЛомбард (товары, филиалы, удаления)
- **Логирование** — все входящие запросы пишутся в БД с флагами авторизации и обработки
- **Административная панель** (EasyAdmin) — скрытые/проданные/изъятые товары, филиалы, логи Push API
- **Отдельная авторизация** в админку (независимо от витрины)

---

## 🚀 Установка

### 1. Клонировать репозиторий

```bash
git clone https://github.com/dispropele/smart-soft-pushapi.git
cd smart-soft-pushapi
```

### 2. Установить зависимости

```bash
composer install
```

### 3. Настроить окружение

Отредактируйте `.env`:

```dotenv
APP_ENV=dev
DEFAULT_URI=http://127.0.0.1

DATABASE_URL="postgresql://login:password@127.0.0.1:5432/komissionka?serverVersion=18&charset=utf8"

SMARTLOMBARD_API_SECRET=ваш_секретный_ключ
```

### 4. Создать базу данных и выполнить миграции

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Создать администратора

```bash
php bin/console app:create-admin <username> <email> <password>
```

### 7. Запустить сервер

```bash
# Symfony CLI
symfony server:start

# Или встроенный PHP-сервер
php -S 127.0.0.1:8000 -t public/
```

Сайт будет доступен по адресу **http://localhost:8000**

---

## ⚙️ Подключение Push API (СмартЛомбард)

Для приёма данных в реальном времени нужен публичный URL. В разработке используйте **ngrok**:

```bash

# Добавить токен (получить на https://dashboard.ngrok.com)
ngrok config add-authtoken ВАШ_ТОКЕН

# Запустить туннель
ngrok http 8000
```

URL для Push API в настройках СмартЛомбард:
```
https://xxxx-xx-xx-xx-xx.ngrok-free.app/api/push
```

---

## 📁 Структура проекта

```
src/
├── Controller/
│   ├── CatalogController.php        # Витрина, корзина (GET/POST)
│   ├── PushApiController.php        # POST /api/push — приём данных
│   └── Admin/
│       ├── DashboardController.php  # Главная страница /admin
│       ├── AdminLoginController.php # /admin/login
│       ├── GoodCrudController.php   # Скрытые/проданные товары
│       ├── MerchantCrudController.php
│       └── PushApiLogCrudController.php
├── Entity/
│   ├── Good.php           # Товар
│   ├── GoodImage.php      # Изображения товара
│   ├── Merchant.php       # Филиал
│   ├── City.php           # Город
│   ├── Category.php       # Категория
│   ├── Metal.php          # Металл
│   ├── MetalStandard.php  # Проба
│   ├── Currency.php       # Валюта
│   ├── Admin.php          # Администратор панели
│   └── PushApiLog.php     # Лог входящих запросов
├── Repository/
│   └── GoodRepository.php # Поиск, пагинация, сортировка
└── Service/
    └── SmartLombardHandler.php  # Обработка вебхука, скачивание фото

templates/
├── base.html.twig               # Шапка, поиск, корзина, навигация
├── catalog/
│   ├── index.html.twig          # Каталог товаров
│   ├── show.html.twig           # Карточка товара
│   └── cart.html.twig           # Корзина
└── admin/
    ├── login.html.twig          # Страница входа в админку
    ├── dashboard.html.twig      # Дашборд
    └── field/
        ├── good_cover.html.twig
        └── merchant_logo.html.twig
```

---

## 🌐 Маршруты

| Метод | URL | Описание |
|-------|-----|----------|
| GET | `/` | Каталог товаров |
| GET | `/product/{id}` | Карточка товара |
| GET | `/cart` | Корзина |
| POST | `/cart/add/{id}` | Добавить в корзину |
| POST | `/cart/remove/{id}` | Удалить из корзины |
| GET | `/cart/count` | Кол-во в корзине (JSON) |
| **POST** | **`/api/push`** | **Push API от СмартЛомбард** |
| GET | `/admin` | Административная панель |
| GET | `/admin/login` | Вход в админку |
| GET | `/admin/logout` | Выход из админки |


---
