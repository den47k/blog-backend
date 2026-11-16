# Wisp Backend

This is the **Laravel 12** backend for a real-time chat application.  
It supports **private conversations**, **file attachments**, and **real-time messaging** powered by **Centrifugo** and **WebSockets**.  
The backend also integrates **MinIO** for object storage (user avatars, attachments), **PostgreSQL** as the database and **Redis** for caching.

---

## Features

- **Private conversations** between users.
- **File attachments** in messages (images, documents, etc.).
- **Real-time messaging** using Centrifugo.
- **Token authentication** with Laravel Sanctum (bearer tokens, one per device).
- **Registration with e-mail verification**, password reset.
- **Two-factor authentication** (TOTP + recovery codes).
- **Sign in with Google / GitHub**.
- **MinIO S3 storage** for avatars and attachments.
- **Dockerized development** using Laravel Sail.

---

## Requirements

- **Docker & Docker Compose**
- **PHP 8.2+** (if running without Docker)
- **Composer**

---

## Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/den47k/blog-backend.git
   cd blog-backend
    ```

2. **Install PHP dependencies**
    
    ```bash
    composer install
    ```
    
3. **Copy environment file**
    
    ```bash
    cp .env.example .env
    ```

   Fill in `CENTRIFUGO_API_KEY` and `CENTRIFUGO_TOKEN_HMAC_SECRET` (any random strings),
   the `AWS_*` keys (MinIO user `sail` / `password` by default) and `AWS_BUCKET`.
   The `GOOGLE_*` / `GITHUB_*` keys are only needed for social sign-in.

4. **Create the shared Docker network**

    ```bash
    docker network create blog-network
    ```
    
5. **Start Docker containers**
    
    ```bash
    ./vendor/bin/sail up -d
    ```
    
6. **Generate application key**
    
    ```bash
    ./vendor/bin/sail artisan key:generate
    ```
    
7. **Run database migrations**
    
    ```bash
    ./vendor/bin/sail artisan migrate
    ```

8. **Create the storage bucket**

    Open the MinIO console at http://localhost:8900 and create the bucket named in `AWS_BUCKET`.

The API is available at http://localhost/api. The websocket server (Centrifugo) starts together with the other containers on port 8000.

---

## Tests

Tests use a separate `testing` database. Create it once, then run the suite:

```bash
./vendor/bin/sail exec pgsql createdb -U sail testing
./vendor/bin/sail artisan test
```

---

## Authentication

**Laravel Sanctum** bearer tokens are used. The client sends `Authorization: Bearer <token>` with every request.  
CORS is configured for the frontend domain (`CORS_ALLOWED_ORIGINS`).

---

## File Storage

**MinIO** is used as S3-compatible storage.
