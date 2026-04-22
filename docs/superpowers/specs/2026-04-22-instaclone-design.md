# InstaClone — Design Spec

**Date:** 2026-04-22  
**Architecture:** MVC + Service Layer  

---

## 1. Stack

| Component | Choice |
|---|---|
| Runtime | PHP 8.3 + FrankenPHP (`dunglas/frankenphp:1-php8.3-alpine`) |
| Framework | Laravel 11 |
| Database | MySQL 8 |
| Auth | Laravel Sanctum (API tokens via JSON) |
| Storage | MinIO (S3-compatible, local dev) |
| Realtime | Mercure SSE (built into FrankenPHP) |
| Infra | Docker multi-stage build + compose.yaml |

---

## 2. Infraestrutura Docker

### Serviços (`compose.yaml`)

- **app** — FrankenPHP, porta 80/443 (HTTP/HTTPS), porta 3000 (Mercure SSE hub)
- **mysql** — MySQL 8, healthcheck via `mysqladmin ping`, volume persistente
- **minio** — MinIO, porta 9000 (S3 API) + 9001 (console web)

### Dockerfile (multi-stage)

**Stage `builder`:**
- Imagem base: `composer:latest`
- `composer install --no-dev --optimize-autoloader`

**Stage `runtime`:**
- Imagem base: `dunglas/frankenphp:1-php8.3-alpine`
- Extensões PHP: `pdo_mysql`, `intl`, `opcache`, `pcntl`
- Copia `vendor/` do stage builder
- Entrypoint: `docker/entrypoint.sh`

### `docker/entrypoint.sh`

1. Loop TCP aguarda MySQL (`mysqladmin ping -h mysql`)
2. `php artisan migrate --force`
3. `php artisan storage:link`
4. `frankenphp run --config /etc/caddy/Caddyfile`

### `docker/php.ini`

```ini
opcache.enable=1
opcache.validate_timestamps=0
upload_max_filesize=10M
post_max_size=10M
```

### Variáveis de ambiente chave

```dotenv
FILESYSTEM_DISK=s3
AWS_BUCKET=instaclone
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

MERCURE_URL=http://app:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=supersecret
```

---

## 3. Modelagem de Dados

### Tabelas e colunas

**`users`**
- `id`, `name`, `username` (unique), `email` (unique), `password`
- `bio` (nullable text), `avatar` (nullable string — S3 key)
- `timestamps`

**`follows`** (pivot self-join)
- `follower_id` FK → users
- `following_id` FK → users
- `created_at`
- PK composta: (`follower_id`, `following_id`)

**`posts`**
- `id`, `user_id` FK → users
- `caption` (nullable text), `image_path` (string, NOT NULL — S3 key, obrigatório no upload)
- `likes_count` (unsignedInt default 0), `comments_count` (unsignedInt default 0)
- `timestamps`

**`likes`**
- `id`, `user_id` FK → users, `post_id` FK → posts
- `created_at`
- Unique: (`user_id`, `post_id`)

**`comments`**
- `id`, `user_id` FK → users, `post_id` FK → posts
- `body` (text)
- `timestamps`

**`notifications`**
- `id`, `user_id` FK → users
- `type` (enum: `like`, `comment`, `follow`)
- `data` (JSON — contém actor_id, post_id opcional, etc.)
- `read_at` (nullable timestamp)
- `timestamps`

### Indexes

```sql
INDEX follows(follower_id)
INDEX follows(following_id)
INDEX posts(user_id, created_at DESC)
INDEX likes(post_id)
INDEX comments(post_id)
```

### Relacionamentos Eloquent

```php
// User
hasMany(Post::class)
hasMany(Notification::class)
belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->as('following')
belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->as('followers')

// Post
belongsTo(User::class)
hasMany(Like::class)
hasMany(Comment::class)

// Like / Comment
belongsTo(User::class)
belongsTo(Post::class)

// Notification
belongsTo(User::class)
```

---

## 4. Lógica de Negócio

### `FeedService::getFeed(User $user)`

```php
Post::whereIn('user_id', $user->following()->pluck('users.id'))
    ->with([
        'user:id,name,username,avatar',
        'likes' => fn($q) => $q->where('user_id', $user->id),
    ])
    ->latest()
    ->cursorPaginate(15);
```

- `whereIn` dos IDs seguidos (pluck evita subquery desnecessária)
- Eager load do user do post (campos mínimos) e like do usuário autenticado (para saber se curtiu)
- Cursor pagination: mais eficiente que offset em datasets grandes
- `likes_count` / `comments_count` lidos do campo cacheado — sem `COUNT(*)` no feed

### `NotificationService::notify(User $recipient, string $type, array $data)`

1. Cria registro em `notifications`
2. Publica no Mercure topic `users/{recipient->id}/notifications` com JWT assinado

---

## 5. Endpoints da API

Prefixo: `/api/v1` — todas as rotas autenticadas usam middleware `auth:sanctum`.

| Grupo | Método | Rota | Auth | Policy |
|---|---|---|---|---|
| Auth | POST | `/register` | — | — |
| Auth | POST | `/login` | — | — |
| Auth | POST | `/logout` | sim | — |
| Profile | GET | `/users/{user}` | — | — |
| Profile | PUT | `/users/{user}` | sim | `UserPolicy::update` |
| Social | POST | `/users/{user}/follow` | sim | — |
| Social | DELETE | `/users/{user}/follow` | sim | — |
| Social | GET | `/users/{user}/followers` | — | — |
| Social | GET | `/users/{user}/following` | — | — |
| Feed | GET | `/feed` | sim | — |
| Posts | POST | `/posts` | sim | — |
| Posts | GET | `/posts/{post}` | — | — |
| Posts | PUT | `/posts/{post}` | sim | `PostPolicy::modify` |
| Posts | DELETE | `/posts/{post}` | sim | `PostPolicy::modify` |
| Likes | POST | `/posts/{post}/likes` | sim | — |
| Likes | DELETE | `/posts/{post}/likes` | sim | — |
| Comments | GET | `/posts/{post}/comments` | — | — |
| Comments | POST | `/posts/{post}/comments` | sim | — |
| Comments | PUT | `/comments/{comment}` | sim | `CommentPolicy::modify` |
| Comments | DELETE | `/comments/{comment}` | sim | `CommentPolicy::modify` |
| Notifications | GET | `/notifications` | sim | — |
| Notifications | PUT | `/notifications/{notification}/read` | sim | `NotificationPolicy::own` |

---

## 6. Segurança

### Policies

- **`PostPolicy::modify`**: `$user->id === $post->user_id`
- **`CommentPolicy::modify`**: `$user->id === $comment->user_id`
- **`UserPolicy::update`**: `$user->id === $targetUser->id`
- **`NotificationPolicy::own`**: `$user->id === $notification->user_id`

Registradas via `Gate::policy()` no `AppServiceProvider`.

---

## 7. Notificações em Tempo Real (Mercure SSE)

### Fluxo

1. Observer dispara no evento (`LikeObserver::created`, `CommentObserver::created`, `FollowObserver::created`)
2. Observer chama `NotificationService::notify()`
3. `NotificationService` cria registro em `notifications` + publica no Mercure hub
4. Cliente JS faz `new EventSource(mercureUrl + '?topic=users/{id}/notifications', { headers: { Authorization: 'Bearer ...' } })`

### Tópico Mercure

```
Topic: users/{user_id}/notifications
Payload: { type, actor, post_id?, created_at }
```

JWT Mercure assinado com `MERCURE_JWT_SECRET`, escopo restrito ao próprio `user_id`.

---

## 8. Estrutura de Diretórios Laravel

```
app/
  Http/
    Controllers/
      AuthController.php
      UserController.php
      PostController.php
      CommentController.php
      LikeController.php
      NotificationController.php
    Requests/           # FormRequests para validação
    Resources/          # API Resources para transformação
  Models/
    User.php  Post.php  Like.php  Comment.php  Notification.php
  Policies/
    PostPolicy.php  CommentPolicy.php  UserPolicy.php  NotificationPolicy.php
  Services/
    FeedService.php
    NotificationService.php
  Observers/
    LikeObserver.php  CommentObserver.php  FollowObserver.php
docker/
  entrypoint.sh
  php.ini
docs/
  superpowers/specs/
    2026-04-22-instaclone-design.md
Dockerfile
compose.yaml
```
