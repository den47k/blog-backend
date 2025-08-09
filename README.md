# Whisp Backend

This is the **Laravel 12** backend for a real-time chat application.  
It supports **private conversations**, **file attachments**, and **real-time messaging** powered by **Laravel Reverb** and **WebSockets**.  
The backend also integrates **MinIO** for object storage (user avatars, attachments) and **Redis** for caching/presence.

---

## 🚀 Features

- **Private conversations** between users.
- **File attachments** in messages (images, documents, etc.).
- **Real-time messaging** using Laravel Reverb.
- **Presence channels** for tracking online users.
- **Secure authentication** with Laravel Sanctum.
- **MinIO S3 storage** for avatars and attachments.
- **Dockerized development** using Laravel Sail.

---

## 📦 Requirements

- **Docker & Docker Compose**
- **PHP 8.2+** (if running without Docker)
- **Composer**

---

## ⚙️ Installation

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
    
4. **Start Docker containers**
    
    ```bash
    ./vendor/bin/sail up -d
    ```
    
5. **Generate application key**
    
    ```bash
    ./vendor/bin/sail artisan key:generate
    ```
    
6. **Run database migrations**
    
    ```bash
    ./vendor/bin/sail artisan migrate
    ```

7. **Run websocket server**
    
    ```bash
    ./vendor/bin/sail artisan reverb:start
    ```

---

## 🔒 Authentication

**Laravel Sanctum** was used for SPA token-based authentication.  
CORS is configured for the frontend domain.

---

## 🗂️ File Storage

**MinIO** is used as S3-compatible storage.
    
