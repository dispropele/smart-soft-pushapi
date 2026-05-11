# Развертывание на Render

Этот проект настроен для развертывания на платформе [Render](https://render.com). Ниже приведены инструкции по развертыванию.

## Прежде чем начать

1. Создайте аккаунт на [Render.com](https://render.com)
2. Подготовьте GitHub репозиторий с этим проектом
3. Сгенерируйте значение `APP_SECRET`:
   ```bash
   php -r 'echo bin2hex(random_bytes(16));'
   ```

## Метод 1: Развертывание через render.yaml (Рекомендуется)

### Шаг 1: Создайте Blueprint

1. Зайдите в [Render Dashboard](https://dashboard.render.com/)
2. Нажмите **+ New →  Blueprint**
3. Выберите свой GitHub репозиторий

### Шаг 2: Конфигурация

Render автоматически обнаружит `render.yaml` и создаст:
- **Web Service** (приложение) на порту 8080
- **PostgreSQL Database** (версия 16)

### Шаг 3: Переменные окружения

Убедитесь, что установлены следующие переменные в Web Service:

```
APP_ENV=prod
APP_SECRET=<ваше-сгенерированное-значение>
SYMFONY_ENV=prod
```

Переменные для базы данных будут автоматически добавлены из конфигурации БД.

### Шаг 4: Развертывание

Нажмите **Deploy** и ждите завершения.

## Метод 2: Ручное развертывание на Render

### Шаг 1: Создайте Web Service

1. В Render Dashboard: **+ New → Web Service**
2. Выберите ваш GitHub репозиторий
3. Заполните поля:
   - **Name**: smart-soft-pushapi
   - **Runtime**: Docker
   - **Build Command**: (оставить пустым, используется Dockerfile)
   - **Start Command**: (оставить пустым, используется ENTRYPOINT)
   - **Plan**: Standard или выше

### Шаг 2: Создайте PostgreSQL Database

1. В Render Dashboard: **+ New → PostgreSQL**
2. Заполните поля:
   - **Name**: smart-soft-db
   - **Database**: app
   - **User**: app
   - **PostgreSQL Version**: 16
   - **Region**: выберите регион

### Шаг 3: Подключите переменные окружения

В Web Service добавьте переменные окружения:

```
APP_ENV=prod
APP_SECRET=<сгенерированное-значение>
SYMFONY_ENV=prod
DATABASE_HOST=<хост-БД-из-Render>
DATABASE_PORT=5432
DATABASE_NAME=app
DATABASE_USER=app
DATABASE_PASSWORD=<пароль-из-Render>
```

**Или** используйте типы переменных "From PostgreSQL" для автоматического подключения.

### Шаг 4: Развертывание

Commit и push изменения в GitHub. Render автоматически развернет приложение.

## После развертывания

### Проверка здоровья приложения

Перейдите по адресу: `https://your-app.onrender.com/health`

Вы должны увидеть ответ: `healthy`

### Просмотр логов

В Render Dashboard перейдите в Web Service и откройте вкладку **Logs**.

### Запуск консольных команд

Используйте Shell в Render Dashboard:

```bash
# Проверить статус миграций
php bin/console doctrine:migrations:status

# Запустить миграции вручную
php bin/console doctrine:migrations:migrate

# Очистить кэш
php bin/console cache:clear

# Создать администратора
php bin/console app:create-admin
```

## Загрузка файлов

Загрузка файлов сохраняется в директории `public/uploads/`. **Важно**: на Render это временная файловая система. Для долгосрочного хранения используйте:

- AWS S3
- Render's persistent disk (добавьте в render.yaml)
- Другой облачный сервис

### Добавление persistent disk (опционально)

Добавьте в `render.yaml` в секцию `services`:

```yaml
disk:
  name: uploads
  mountPath: /app/public/uploads
  sizeGb: 10
```

## Проблемы при развертывании

### Ошибка: "Database connection failed"

- Проверьте переменные окружения `DATABASE_*`
- Убедитесь, что PostgreSQL сервис запущен
- Проверьте IP allowlist в PostgreSQL сервисе

### Ошибка: "Class not found"

- Composer автоматически запускается при сборке
- Проверьте файл `composer.json` на синтаксические ошибки

### Ошибка: "Permission denied"

- Убедитесь, что директории `var/` и `public/uploads/` доступны для записи
- Это должно быть обработано автоматически Dockerfile

## Обновление и переразвертывание

1. Сделайте изменения в коде
2. Закоммитьте и push в GitHub
3. Render автоматически перестроит и переразвернет приложение

Или вручную нажмите **Manual Deploy** в Render Dashboard.

## Дополнительно

- [Документация Render](https://render.com/docs)
- [Документация Docker](https://docs.docker.com/)
- [Документация Symfony для production](https://symfony.com/doc/current/deployment.html)
