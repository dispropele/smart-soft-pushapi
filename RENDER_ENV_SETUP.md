# Как установить переменные БД на Render

## Шаг 1: Получите данные подключения PostgreSQL

1. Зайдите на **Render Dashboard**
2. Перейдите в ваш **PostgreSQL сервис** (smart-soft-db или как он называется)
3. В секции **Info** найдите **External Database URL**
4. Скопируйте значение - оно должно выглядеть так:
   ```
   postgresql://username:password@host.render.com:5432/database_name
   ```

## Шаг 2: Распарсьте URL и получите значения

Из URL вида: `postgresql://app:password123@postgres-abc123.render.com:5432/app`

Извлеките:
- **DATABASE_HOST** = `postgres-abc123.render.com` (хост)
- **DATABASE_PORT** = `5432` (обычно 5432)
- **DATABASE_NAME** = `app` (имя БД)
- **DATABASE_USER** = `app` (пользователь)
- **DATABASE_PASSWORD** = `password123` (пароль)

## Шаг 3: Установите переменные в Web Service

1. В Render Dashboard откройте **Web Service** (smart-soft-pushapi)
2. Перейдите на вкладку **Environment**
3. Добавьте следующие переменные:

| Ключ | Значение |
|------|----------|
| DATABASE_HOST | postgres-abc123.render.com |
| DATABASE_PORT | 5432 |
| DATABASE_NAME | app |
| DATABASE_USER | app |
| DATABASE_PASSWORD | password123 |

(Замените на реальные значения из вашего External Database URL)

## Шаг 4: Перезапустите приложение

1. Нажмите **Manual Deploy** в Web Service
2. Дождитесь завершения развертывания
3. Проверьте логи - должны быть видны попытки подключения к правильному хосту БД

## Как проверить что работает

В логах должно быть:
```
Database configuration:
  HOST: postgres-abc123.render.com
  PORT: 5432
  USER: app

Running migrations...
Database connection successful!
```

Если все еще видно `localhost:5432` - значит переменные не установлены.

## Альтернатива: Использовать DATABASE_URL

Вместо отдельных переменных можно использовать единую переменную:

```
DATABASE_URL=postgresql://app:password@postgres-abc123.render.com:5432/app
```

И обновить entrypoint скрипт для парсинга этой переменной (требует изменений в коде).
