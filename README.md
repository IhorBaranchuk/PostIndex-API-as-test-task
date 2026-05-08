# Post Index API

REST API для роботи з поштовими індексами України. Дані імпортуються з офіційного архіву [Укрпошти](https://www.ukrposhta.ua/files/shares/out/postindex.zip).

## Стек

- **PHP 8.3** + [Slim Framework 4](https://www.slimframework.com/)
- **MySQL / MariaDB 11** — зберігання даних, робота через **PDO**
- **Docker** + **Nginx**
- **Vue 3** (CDN) — мінімальний веб-інтерфейс
- **Swagger UI** — документація API

---

## Розгортання

### Вимоги

- Docker
- Docker Compose

### Перший запуск

```bash
# 1. Клонувати репозиторій
git clone https://github.com/IhorBaranchuk/PostIndex-API-as-test-task.git
cd PostIndex-API-as-test-task

# 2. Підняти контейнери, встановити залежності, завантажити дані і запустити імпорт
make fresh
```

`make fresh` виконує повний цикл:
1. `docker compose down -v` — зносить старі контейнери і томи
2. `docker compose up -d --build` — збирає і піднімає контейнери
3. `composer install` — встановлює PHP-залежності
4. `make download` — завантажує актуальний архів з сайту Укрпошти
5. `php bin/import.php` — імпортує дані в БД

Після цього API і веб-інтерфейс одразу готові до роботи з актуальними даними.

База даних ініціалізується автоматично з файлу `database/init.sql` при першому старті контейнера.

### Наступні запуски

```bash
make up      # підняти контейнери
make down    # зупинити контейнери
make restart # перезібрати і перезапустити
```

---

## Доступні адреси

| Сервіс | URL |
|---|---|
| **Веб-інтерфейс** (Vue) | http://localhost:8080/app.html |
| **Swagger UI** | http://localhost:8081 |
| **MySQL** (зовнішній порт) | `localhost:3308` |

---

## Імпорт поштових індексів

Скрипт імпорту завантажує дані з zip-архіву, порівнює з поточним станом БД і застосовує зміни:

- додає відсутні записи
- оновлює змінені
- видаляє записи яких немає в архіві *(крім доданих через API)*

### Запуск

```bash
# З архівом за замовчуванням (storage/imports/postindex.zip)
make import

# Або з власним шляхом до архіву
docker compose exec app php bin/import.php /path/to/postindex.zip
```

### Результат

```json
{
    "import_id": "20260507151045_fc689c67",
    "processed": 27845,
    "skipped": 12,
    "deleted": 3
}
```

### Автоматичний запуск (cron)

```bash
# Щодня о 03:00
0 3 * * * docker compose -f /path/to/docker-compose.yml exec -T app php bin/import.php
```

---

## API

Повна документація доступна у **Swagger UI** → http://localhost:8081

### Короткий огляд

#### `GET /post-indexes` — отримати список

| Параметр | Тип | Опис |
|---|---|---|
| *(без параметрів)* | — | 50 записів на сторінку, сортування за індексом |
| `page` | integer | Номер сторінки |
| `post_code` | string | Пошук за точним індексом |
| `address` | string | Пошук за адресою або її частиною |

```bash
# Всі записи, 1-ша сторінка
curl http://localhost:8080/post-indexes

# Конкретний індекс
curl http://localhost:8080/post-indexes?post_code=01001

# Пошук за адресою
curl "http://localhost:8080/post-indexes?address=Вінницька"

# Пагінація
curl http://localhost:8080/post-indexes?page=2
```

---

#### `POST /post-indexes` — додати або оновити

Приймає один об'єкт або масив. Поле `post_code` обов'язкове, має містити рівно 5 цифр.

```bash
# Один запис
curl -X POST http://localhost:8080/post-indexes \
  -H "Content-Type: application/json" \
  -d '{"post_code":"01001","region":"Київська","district":"Києво-Святошинський","locality":"м. Київ"}'

# Декілька записів
curl -X POST http://localhost:8080/post-indexes \
  -H "Content-Type: application/json" \
  -d '[{"post_code":"01001","region":"Київська"},{"post_code":"79000","region":"Львівська"}]'
```

---

#### `DELETE /post-indexes` — видалити

```bash
# Один запис
curl -X DELETE http://localhost:8080/post-indexes \
  -H "Content-Type: application/json" \
  -d '{"post_code":"01001"}'

# Декілька записів
curl -X DELETE http://localhost:8080/post-indexes \
  -H "Content-Type: application/json" \
  -d '{"post_codes":["01001","79000"]}'
```

---

## Тести

Проект покритий юніт-тестами на **PHPUnit 11**.

```bash
make test
```

### Структура тестів

```
tests/
├── Support/
│   ├── FakeRowIterator.php          # Тест-дабл для RowIteratorInterface
│   └── TestableImportService.php    # Підклас для тестування без файлових операцій
└── Unit/
    ├── PostIndexControllerTest.php  # HTTP-шар (статуси, формат відповіді, парсинг тіла)
    ├── PostIndexServiceTest.php     # Бізнес-логіка (getList, createMany, deleteMany)
    └── PostIndexXlsxImportServiceTest.php  # Логіка імпорту (маппінг, транзакції, помилки)
```

Для запуску окремого файлу:

```bash
docker compose exec app ./vendor/bin/phpunit --testdox tests/Unit/PostIndexServiceTest.php
```

---

## Структура проекту

```
postindex-api/
├── app/
│   ├── Contracts/          # Інтерфейси
│   ├── Controllers/        # HTTP-контролери (Slim)
│   ├── Enums/              # PostIndexSource (api / archive)
│   ├── Repositories/       # Робота з БД через PDO
│   ├── Services/           # Бізнес-логіка та імпорт
│   ├── Support/            # XlsxRowIterator — потоковий парсер XLSX
│   └── Database.php        # PDO-підключення
├── bin/
│   └── import.php          # Точка входу для імпорту
├── database/
│   └── init.sql            # Схема БД
├── docs/
│   └── openapi.yaml        # OpenAPI 3.0 специфікація
├── public/
│   ├── index.php           # Точка входу API
│   └── app.html            # Vue 3 веб-інтерфейс
├── tests/
│   ├── Support/            # Тест-дабли
│   └── Unit/               # Юніт-тести
├── docker/
│   └── nginx.conf
├── docker-compose.yml
├── Dockerfile
└── Makefile
```

---

## Корисні команди

```bash
make up       # запустити контейнери
make down     # зупинити контейнери
make fresh    # повний перезапуск з нуля (видаляє томи БД, завантажує дані, імпортує)
make download # завантажити актуальний архів з сайту Укрпошти
make import   # запустити імпорт з наявного архіву
make test     # запустити юніт-тести
make logs     # переглянути логи
make bash     # зайти в контейнер app
make db       # підключитись до MariaDB
```
