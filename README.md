# MicroPayment

Учебный эмулятор платёжного процессора. Демонстрирует REST API, событийную
архитектуру на Kafka, транзакции PostgreSQL, идемпотентность через Redis и
балансировку нагрузки за Nginx.

## Стек
```
PHP 8.4
Symfony 7
PostgreSQL 16
Redis 7
Apache Kafka (KRaft)
Nginx
Docker Compose
LexikJWT
Symfony Messenger
PHPUnit
```
## Процессинг
Операции асинхронны: HTTP-запрос публикует команду в Kafka и сразу
отвечает `202` со сгенерированным `id`. Воркер потребляет команду, пишет строку
`transaction` в статусе `PENDING`, затем админ проводит (`approve`) или блокирует
(`block`) её — тоже через очередь. Деньги списываются/зачисляются только при
`approve`, в одной транзакции PostgreSQL — кошельки на это время блокируются, 
поэтому параллельные операции не мешают друг другу.
После изменения статуса воркер публикует доменное событие в Kafka, которое
асинхронно обрабатывают consumer'ы (уведомления, аудит).

Доставка at-least-once: авто-коммит оффсетов выключен, оффсет двигается только в
`ack()`/`reject()` транспорта (`KafkaTransport`), то есть после того как хендлер
отработал либо Messenger исчерпал ретраи и увёл сообщение в failed-транспорт.
Падение воркера посреди обработки означает повторную выдачу сообщения, а не потерю.

## Запуск

```bash
cp .env.example .env   # заполнить APP_SECRET и JWT_PASSPHRASE
docker compose up -d --build
```

Поднимаются: `postgres`, `redis`, `kafka`, `app1`, `app2`, `worker`, `nginx`.
Миграции применяются автоматически (контейнер `app1`), JWT-ключи запекаются в
образ. API доступен на `http://localhost:8080`.

Swagger UI: `http://localhost:8080/api/doc`

## Деньги

Хранятся в минимальных единицах (центах), тип `bigint`. `amount: 5000` = 50.00.

## Redis

Используется под идемпотентность создания транзакций. `IdempotencyService` хранит связку
`<userId>:<Idempotency-Key> -> id транзакции` (TTL 3 суток; ключ скоупится по пользователю,
чтобы одинаковый заголовок у разных клиентов не пересекался): повторный запрос
`deposit`/`withdraw`/`transfer` с тем же ключом возвращает уже созданный id вместо
второй транзакции.

Заявка на ключ делается атомарно через `SET key value NX EX` — из нескольких параллельных
запросов с одним ключом выигрывает один, остальные получают его же id, поэтому дублей не
возникает. Если публикация команды в Kafka не удалась, ключ освобождается (`release`), чтобы
он не указывал на транзакцию, которой не будет. Долгоживущая страховка — уникальный индекс
`idempotency_key` в БД: если запись в Redis уже истекла, повтор находится по нему.

## Сценарий (curl)

Плейсхолдеры `{{token}}`, `{{wallet}}`, `{{other}}`, `{{tx}}` подставить из ответов.

```bash
# 1. Регистрация (всегда ROLE_USER)
curl -X POST http://localhost:8080/api/v1/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice3@example.com","password":"secret123"}'

# 2. Логин админом (создан миграцией-сидом, см. ниже) -> {{admin_token}}
curl -X POST http://localhost:8080/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@micropayment.local","password":"<ADMIN_PASSWORD>"}'

# 3. Логин -> JWT (поле "token" в ответе)
curl -X POST http://localhost:8080/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.com","password":"secret123"}'

# 4. Кошелёк (создать два: отправитель и получатель; "id" в ответе)
curl -X POST http://localhost:8080/api/v1/wallets \
  -H 'Authorization: Bearer {{token}}' \
  -H 'Content-Type: application/json' \
  -d '{"currency":"USD"}'

# 5. Пополнение — 202 + "id" транзакции (Idempotency-Key защищает от дублей)
curl -X POST http://localhost:8080/api/v1/transactions/deposit \
  -H 'Authorization: Bearer {{token}}' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: dep-1' \
  -d '{"walletId":"{{wallet}}","amount":10000}'

# 6. Апрув админом — воркер зачисляет средства. Нужен токен с ROLE_ADMIN.
curl -X POST http://localhost:8080/api/v1/transactions/{{tx}}/approve \
  -H 'Authorization: Bearer {{admin_token}}'

# 7. Статус транзакции — PENDING -> APPROVED / FAILED / BLOCKED
curl -X GET http://localhost:8080/api/v1/transactions/{{tx}} \
  -H 'Authorization: Bearer {{token}}'

# 8. Баланс (после обработки воркером)
curl -X GET http://localhost:8080/api/v1/wallets/{{wallet}} \
  -H 'Authorization: Bearer {{token}}'

# 9. Вывод — 202 + "id" транзакции в PENDING (списание тоже только после approve)
curl -X POST http://localhost:8080/api/v1/transactions/withdraw \
  -H 'Authorization: Bearer {{token}}' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: wd-1' \
  -d '{"walletId":"{{wallet}}","amount":2500}'

# 10. Апрув вывода админом -> средства списываются
curl -X POST http://localhost:8080/api/v1/transactions/{{tx}}/approve \
  -H 'Authorization: Bearer {{admin_token}}'

# 11. Перевод — 202 + "id" транзакции в PENDING
curl -X POST http://localhost:8080/api/v1/transactions/transfer \
  -H 'Authorization: Bearer {{token}}' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: tr-1' \
  -d '{"senderWalletId":"{{wallet}}","recipientWalletId":"{{other}}","amount":3000}'

# 12. Апрув перевода админом
curl -X POST http://localhost:8080/api/v1/transactions/{{tx}}/approve \
  -H 'Authorization: Bearer {{admin_token}}'

# 13. Либо блокировка админом вместо апрува -> BLOCKED (деньги не двигаются)
curl -X POST http://localhost:8080/api/v1/transactions/{{tx}}/block \
  -H 'Authorization: Bearer {{admin_token}}'

# 14. Возврат — инициирует владелец кошелька (создаёт REFUND в PENDING, апрувит админ)
curl -X POST http://localhost:8080/api/v1/transactions/{{tx}}/refund \
  -H 'Authorization: Bearer {{token}}'

# 15. Профиль — текущий пользователь и его транзакции
curl http://localhost:8080/api/v1/profile \
  -H 'Authorization: Bearer {{token}}'
```

## Эндпоинты

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/api/v1/register` | регистрация |
| POST | `/api/v1/login` | получить JWT |
| GET | `/api/v1/profile` | текущий пользователь + его транзакции |
| POST | `/api/v1/wallets` | создать кошелёк |
| GET | `/api/v1/wallets/{id}` | баланс |
| POST | `/api/v1/transactions/deposit` | пополнение → `202`, `PENDING` |
| POST | `/api/v1/transactions/withdraw` | вывод → `202`, `PENDING` |
| POST | `/api/v1/transactions/transfer` | перевод → `202`, `PENDING` |
| POST | `/api/v1/transactions/{id}/refund` | возврат (владелец) → `202`, `PENDING` |
| POST | `/api/v1/transactions/{id}/approve` | **ROLE_ADMIN**: провести → `APPROVED` |
| POST | `/api/v1/transactions/{id}/block` | **ROLE_ADMIN**: заблокировать → `BLOCKED` |
| GET | `/api/v1/transactions/{id}` | статус транзакции |

Все операции асинхронны: любой из `deposit`/`withdraw`/`transfer`/`refund` публикует
команду в Kafka и возвращает `202` с `id`. Воркер создаёт транзакцию в `PENDING`; деньги
двигаются только после `approve` админом. `GET /api/v1/transactions/{id}` вернёт `404`, пока
воркер не обработал команду. `refund` инициирует владелец кошелька, `approve`/`block` —
только `ROLE_ADMIN`.

Эндпоинты создания принимают заголовок `Idempotency-Key` — повторный запрос с тем же
ключом не создаёт вторую транзакцию.

Транзакция, которую за 3 суток никто не провёл и не заблокировал, блокируется
автоматически: раз в час планировщик (`symfony/scheduler`) отправляет команду
`BlockTransaction` по тем же рельсам, что и админский `block`, поэтому уведомление и
запись в аудит появляются как обычно. Вручную: `make expire`.

## Админ

`/register` всегда создаёт `ROLE_USER` — роль админа через API получить нельзя. Единственный
администратор создаётся миграцией-сидом (`migrations/Version20260730120000.php`): она берёт
`ADMIN_EMAIL` и `ADMIN_PASSWORD` из окружения, проверяет наличие такого пользователя в БД и,
если его нет, вставляет запись с `ROLE_ADMIN`. Если переменные не заданы, сид пропускается —
админа не будет. Повышать других пользователей — вручную в БД.

## Consumer'ы

Воркер `worker` запускает `messenger:consume events_kafka scheduler_default` и обрабатывает
события двумя хендлерами: уведомления и аудит (в `logs.actor` пишется id админа,
сделавшего `approve`/`block`, либо `system` для остальных событий). Второй транспорт — планировщик: раз в час он
выдаёт сообщение `ExpirePendingTransactions`, которое блокирует просроченные транзакции.

```bash
make consume # запустить consumer вручную
make expire  # блокировать висячие PENDING сейчас
```

## Тесты

```bash
make test # docker compose exec app1 php bin/phpunit
```

Гоняются внутри контейнера `app1` на отдельной БД `app_test` (Postgres); Kafka
заменена sync-транспортом Messenger (команды и события исполняются inline). Кэш
приложения в тестах array, а идемпотентность работает с настоящим Redis из стека —
атомарность `SET NX` на array-кэше не проверить.

## Деплой

Простой деплой по SSH на сервере выполняется `git pull` и пересборка стека.

```bash
# один раз: склонировать репозиторий на сервер
git clone <origin-url> /www/micropayment
cp .env.example .env    # заполнить APP_SECRET, JWT_PASSPHRASE, ADMIN_*, APP_ENV=prod
# каждый релиз: выкатить main на сервер
make deploy
```

`deploy.sh` сам `.env` не создаёт: если файла нет, деплой падает с ошибкой — иначе прод
поднялся бы на дефолтах примера, то есть в dev-режиме с предсказуемыми секретами.
