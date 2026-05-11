# Чек-лист развертывания на Render

## ✅ Шаги подготовки проекта

- [ ] Убедитесь, что `composer.json` включает все необходимые пакеты
- [ ] Проверьте, что все миграции находятся в папке `migrations/`
- [ ] Обновите `.gitignore` чтобы не загружать `var/cache` и `var/log`
- [ ] Добавьте `APP_SECRET` в переменные окружения
- [ ] Проверьте, что `public/uploads/` и `var/` доступны для записи

## ✅ Конфигурация Render

### Вариант 1: С использованием render.yaml (рекомендуется)

- [ ] Загрузите проект на GitHub
- [ ] Создайте новый Blueprint в Render Dashboard
- [ ] Выберите GitHub репозиторий
- [ ] Дождитесь автоматического создания Web Service и PostgreSQL
- [ ] Установите переменные окружения:
  - [ ] `APP_ENV=prod`
  - [ ] `APP_SECRET=<сгенерированное-значение>`
  - [ ] `SMARTLOMBARD_API_SECRET=<ваше-значение>`
  - [ ] `DEFAULT_URI=<URL-вашего-приложения>`
- [ ] Нажмите **Deploy**

### Вариант 2: Ручное создание сервисов

- [ ] Загрузьте проект на GitHub
- [ ] Создайте Web Service:
  - [ ] Выберите **Docker** runtime
  - [ ] GitHub репозиторий должен содержать `Dockerfile`
  - [ ] Plan: Standard или выше
- [ ] Создайте PostgreSQL Database:
  - [ ] Version: 16
  - [ ] Database name: app
  - [ ] User: app
- [ ] Подключите Web Service к Database через env vars
- [ ] Установите переменные окружения (см. выше)
- [ ] Deploy

## ✅ После развертывания

- [ ] Проверьте здоровье: `https://your-app.onrender.com/health`
- [ ] Проверьте логи в Render Dashboard
- [ ] Проверьте работу основного функционала:
  - [ ] Главная страница загружается
  - [ ] Каталог работает
  - [ ] Авторизация работает
  - [ ] Админ-панель доступна
- [ ] Проверьте загрузку файлов (если требуется)

## ✅ Первое развертывание

### Через Shell (Render Dashboard)

```bash
# Проверить статус миграций
php bin/console doctrine:migrations:status

# Запустить миграции вручную (если не запустились автоматически)
php bin/console doctrine:migrations:migrate

# Создать администратора
php bin/console app:create-admin

# Очистить кэш
php bin/console cache:clear

# Проверить загрузку ресурсов
curl https://your-app.onrender.com/health
```

## 🔧 Файлы конфигурации

- ✅ `Dockerfile` - конфигурация контейнера
- ✅ `render.yaml` - конфигурация Render Blueprint
- ✅ `.dockerignore` - файлы, исключаемые при сборке
- ✅ `docker/nginx/nginx.conf` - конфигурация Nginx
- ✅ `docker/nginx/conf.d/default.conf` - виртуальный хост
- ✅ `docker/php/php-fpm.conf` - конфигурация PHP-FPM
- ✅ `docker/php/conf.d/opcache.ini` - OPcache конфигурация
- ✅ `.env.render.example` - пример переменных окружения

## 📝 Переменные окружения

```
APP_ENV=prod
APP_SECRET=<сгенерированное>
SMARTLOMBARD_API_SECRET=<значение>
DEFAULT_URI=<URL-приложения>
DATABASE_URL=<автоматически-из-PostgreSQL>
MAILER_DSN=<ваш-mail-сервис>
TZ=UTC
```

## 🆘 Основные проблемы и решения

| Проблема | Решение |
|----------|---------|
| Database connection failed | Проверьте DATABASE_URL и переменные DATABASE_* |
| 502 Bad Gateway | Проверьте логи, убедитесь, что приложение слушает на порту 8080 |
| Файлы не сохраняются | Используйте persistent disk или облачное хранилище (S3) |
| Класс не найден | Перестройте контейнер: Manual Deploy в Dashboard |
| Миграции не запустились | Запустите вручную через Shell |

## 🚀 После успешного развертывания

- [ ] Настройте custom domain (если требуется)
- [ ] Включите auto-deploy при изменении GitHub
- [ ] Настройте email уведомления о ошибках
- [ ] Настройте мониторинг и логирование
- [ ] Документируйте процесс развертывания для команды

---

**Дополнительная информация:**
- [Render Documentation](https://render.com/docs)
- [RENDER_DEPLOY.md](./RENDER_DEPLOY.md) - детальная инструкция
