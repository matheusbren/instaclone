# InstaClone API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a complete RESTful API for InstaClone — auth, social graph (follow/unfollow), content (posts/likes/comments), feed, and real-time notifications via Mercure SSE.

**Architecture:** MVC + Service Layer — thin Controllers delegate to FeedService/NotificationService, Models handle relationships, Policies enforce ownership, Observers dispatch notifications on model events, Mercure SSE delivers real-time push via FrankenPHP built-in hub.

**Tech Stack:** PHP 8.3, Laravel 11, FrankenPHP (`dunglas/frankenphp:1-php8.3-alpine`), MySQL 8, Laravel Sanctum (API tokens), MinIO (S3-compatible), Mercure SSE, `firebase/php-jwt`.

---

## File Map

```
Dockerfile
compose.yaml
docker/
  Caddyfile
  entrypoint.sh
  php.ini
app/
  Http/
    Controllers/
      AuthController.php
      UserController.php
      PostController.php
      CommentController.php
      LikeController.php
      NotificationController.php
    Requests/
      Auth/RegisterRequest.php
      Auth/LoginRequest.php
      Post/StorePostRequest.php
      Post/UpdatePostRequest.php
      Comment/StoreCommentRequest.php
      Comment/UpdateCommentRequest.php
      User/UpdateUserRequest.php
    Resources/
      UserResource.php
      PostResource.php
      CommentResource.php
      NotificationResource.php
  Models/
    User.php          (modify existing)
    Follow.php        (new)
    Post.php          (new)
    Like.php          (new)
    Comment.php       (new)
    Notification.php  (new — custom model, not Laravel's built-in)
  Policies/
    UserPolicy.php
    PostPolicy.php
    CommentPolicy.php
    NotificationPolicy.php
  Services/
    FeedService.php
    NotificationService.php
  Observers/
    FollowObserver.php
    LikeObserver.php
    CommentObserver.php
bootstrap/
  app.php             (modify — apiPrefix + observers)
config/
  mercure.php         (new)
routes/
  api.php
database/
  factories/
    UserFactory.php   (modify — add username)
  migrations/
    *_add_profile_fields_to_users_table.php
    *_create_follows_table.php
    *_create_posts_table.php
    *_create_likes_table.php
    *_create_comments_table.php
    *_create_notifications_table.php
tests/
  Feature/
    Auth/AuthTest.php
    Profile/ProfileTest.php
    Social/FollowTest.php
    Content/PostTest.php
    Content/LikeTest.php
    Content/CommentTest.php
    Feed/FeedTest.php
    Notification/NotificationTest.php
```

---

## Task 1: Docker Infrastructure

**Files:**
- Create: `Dockerfile`
- Create: `compose.yaml`
- Create: `docker/entrypoint.sh`
- Create: `docker/php.ini`
- Create: `docker/Caddyfile`

- [ ] **Step 1: Create Dockerfile**

```dockerfile
# Stage 1: Install dependencies
FROM composer:2 AS builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
COPY . .
RUN composer dump-autoload --optimize

# Stage 2: Runtime
FROM dunglas/frankenphp:1-php8.3-alpine AS runtime
WORKDIR /app

RUN install-php-extensions pdo_mysql intl opcache pcntl

COPY --from=builder /app /app
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
```

- [ ] **Step 2: Create compose.yaml**

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: runtime
    ports:
      - "80:80"
      - "443:443"
    environment:
      APP_KEY: ${APP_KEY}
      APP_ENV: local
      APP_DEBUG: "true"
      APP_URL: http://localhost
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: instaclone
      DB_USERNAME: instaclone
      DB_PASSWORD: secret
      FILESYSTEM_DISK: s3
      AWS_ACCESS_KEY_ID: minioadmin
      AWS_SECRET_ACCESS_KEY: minioadmin
      AWS_BUCKET: instaclone
      AWS_ENDPOINT: http://minio:9000
      AWS_USE_PATH_STYLE_ENDPOINT: "true"
      AWS_DEFAULT_REGION: us-east-1
      MERCURE_URL: http://localhost/.well-known/mercure
      MERCURE_JWT_SECRET: supersecret
    depends_on:
      mysql:
        condition: service_healthy
    volumes:
      - .:/app

  mysql:
    image: mysql:8
    environment:
      MYSQL_DATABASE: instaclone
      MYSQL_USER: instaclone
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "instaclone", "-psecret"]
      interval: 5s
      timeout: 5s
      retries: 10

  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    ports:
      - "9000:9000"
      - "9001:9001"
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin
    volumes:
      - minio_data:/data

volumes:
  mysql_data:
  minio_data:
```

- [ ] **Step 3: Create docker/entrypoint.sh**

```bash
#!/bin/sh
set -e

echo "Waiting for MySQL..."
until mysqladmin ping -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" --silent 2>/dev/null; do
  sleep 2
done
echo "MySQL ready."

php artisan migrate --force
php artisan storage:link || true

exec frankenphp run --config /etc/caddy/Caddyfile
```

- [ ] **Step 4: Create docker/php.ini**

```ini
opcache.enable=1
opcache.validate_timestamps=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
upload_max_filesize=10M
post_max_size=10M
```

- [ ] **Step 5: Create docker/Caddyfile**

```
{
    frankenphp
    order mercure before php_server
}

:80 {
    root * /app/public
    encode zstd br gzip

    mercure {
        publisher_jwt_key {env.MERCURE_JWT_SECRET}
        subscriber_jwt_key {env.MERCURE_JWT_SECRET}
        cors_origins *
    }

    php_server
}
```

- [ ] **Step 6: Commit**

```bash
git init
git add Dockerfile compose.yaml docker/
git commit -m "feat: add Docker infrastructure (FrankenPHP + MySQL + MinIO)"
```

---

## Task 2: Laravel Installation + Package Setup

**Files:**
- Create: all Laravel base files via artisan
- Modify: `bootstrap/app.php`
- Modify: `config/filesystems.php`
- Create: `config/mercure.php`

- [ ] **Step 1: Install Laravel and packages**

```bash
composer create-project laravel/laravel . "11.*"
composer require laravel/sanctum league/flysystem-aws-s3-v3 firebase/php-jwt
php artisan install:api
```

Expected output: `INFO API scaffolding installed. Please add the [Laravel\Sanctum\HasApiTokens] trait to your User model.`

- [ ] **Step 2: Configure bootstrap/app.php — add API prefix**

Replace the `withRouting` call in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/v1',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

- [ ] **Step 3: Create config/mercure.php**

```php
<?php

return [
    'url' => env('MERCURE_URL', 'http://localhost/.well-known/mercure'),
    'jwt_secret' => env('MERCURE_JWT_SECRET', ''),
];
```

- [ ] **Step 4: Configure .env for local dev**

Add to `.env`:

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_BUCKET=instaclone
AWS_ENDPOINT=http://localhost:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_DEFAULT_REGION=us-east-1

MERCURE_URL=http://localhost/.well-known/mercure
MERCURE_JWT_SECRET=supersecret
```

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: bootstrap Laravel 11 with Sanctum, S3, and Mercure config"
```

---

## Task 3: Database Migrations

**Files:**
- Modify: `database/migrations/*_create_users_table.php`
- Create: `database/migrations/*_create_follows_table.php`
- Create: `database/migrations/*_create_posts_table.php`
- Create: `database/migrations/*_create_likes_table.php`
- Create: `database/migrations/*_create_comments_table.php`
- Create: `database/migrations/*_create_notifications_table.php`

- [ ] **Step 1: Modify users migration — add profile fields**

Open `database/migrations/*_create_users_table.php` and add columns inside `Schema::create`:

```php
$table->id();
$table->string('name');
$table->string('username')->unique();
$table->string('email')->unique();
$table->timestamp('email_verified_at')->nullable();
$table->string('password');
$table->text('bio')->nullable();
$table->string('avatar')->nullable();
$table->rememberToken();
$table->timestamps();
```

- [ ] **Step 2: Create follows migration**

```bash
php artisan make:migration create_follows_table
```

Edit the generated file:

```php
public function up(): void
{
    Schema::create('follows', function (Blueprint $table) {
        $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
        $table->timestamp('created_at')->useCurrent();
        $table->primary(['follower_id', 'following_id']);
        $table->index('following_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('follows');
}
```

- [ ] **Step 3: Create posts migration**

```bash
php artisan make:migration create_posts_table
```

```php
public function up(): void
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->text('caption')->nullable();
        $table->string('image_path');
        $table->unsignedInteger('likes_count')->default(0);
        $table->unsignedInteger('comments_count')->default(0);
        $table->timestamps();
        $table->index(['user_id', 'created_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('posts');
}
```

- [ ] **Step 4: Create likes migration**

```bash
php artisan make:migration create_likes_table
```

```php
public function up(): void
{
    Schema::create('likes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('post_id')->constrained()->cascadeOnDelete();
        $table->timestamp('created_at')->useCurrent();
        $table->unique(['user_id', 'post_id']);
        $table->index('post_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('likes');
}
```

- [ ] **Step 5: Create comments migration**

```bash
php artisan make:migration create_comments_table
```

```php
public function up(): void
{
    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('post_id')->constrained()->cascadeOnDelete();
        $table->text('body');
        $table->timestamps();
        $table->index('post_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('comments');
}
```

- [ ] **Step 6: Create notifications migration**

```bash
php artisan make:migration create_notifications_table
```

```php
public function up(): void
{
    Schema::create('notifications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('type'); // like | comment | follow
        $table->json('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
        $table->index(['user_id', 'created_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('notifications');
}
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat: add all database migrations"
```

---

## Task 4: Models + Factory

**Files:**
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Create: `app/Models/Follow.php`
- Create: `app/Models/Post.php`
- Create: `app/Models/Like.php`
- Create: `app/Models/Comment.php`
- Create: `app/Models/Notification.php`

- [ ] **Step 1: Update User model**

Replace `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'bio', 'avatar'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id');
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }
}
```

- [ ] **Step 2: Update UserFactory**

Replace the `definition()` method in `database/factories/UserFactory.php`:

```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'username' => fake()->unique()->userName(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
    ];
}
```

- [ ] **Step 3: Create Follow model**

```bash
php artisan make:model Follow
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follow extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['follower_id', 'following_id'];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
```

- [ ] **Step 4: Create Post model**

```bash
php artisan make:model Post
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'caption', 'image_path'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

- [ ] **Step 5: Create Like model**

```bash
php artisan make:model Like
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Like extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'post_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 6: Create Comment model**

```bash
php artisan make:model Comment
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'post_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 7: Create Notification model**

```bash
php artisan make:model Notification
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 8: Commit**

```bash
git add app/Models/ database/factories/UserFactory.php
git commit -m "feat: add all Eloquent models with relationships"
```

---

## Task 5: Policies + AppServiceProvider

**Files:**
- Create: `app/Policies/UserPolicy.php`
- Create: `app/Policies/PostPolicy.php`
- Create: `app/Policies/CommentPolicy.php`
- Create: `app/Policies/NotificationPolicy.php`
- Create: `app/Observers/FollowObserver.php` (placeholder)
- Create: `app/Observers/LikeObserver.php` (placeholder)
- Create: `app/Observers/CommentObserver.php` (placeholder)
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create placeholder Observers** (must exist before AppServiceProvider imports them)

```bash
php artisan make:observer FollowObserver --model=Follow
php artisan make:observer LikeObserver --model=Like
php artisan make:observer CommentObserver --model=Comment
```

Leave all observer methods empty — they'll be implemented in Task 13.

- [ ] **Step 2: Create UserPolicy**

```bash
php artisan make:policy UserPolicy --model=User
```

Replace content:

```php
<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id;
    }
}
```

- [ ] **Step 2: Create PostPolicy**

```bash
php artisan make:policy PostPolicy --model=Post
```

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function modify(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
```

- [ ] **Step 3: Create CommentPolicy**

```bash
php artisan make:policy CommentPolicy --model=Comment
```

```php
<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function modify(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
```

- [ ] **Step 4: Create NotificationPolicy**

```bash
php artisan make:policy NotificationPolicy --model=Notification
```

```php
<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    public function own(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }
}
```

- [ ] **Step 5: Register policies and observers in AppServiceProvider**

Replace `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Observers\CommentObserver;
use App\Observers\FollowObserver;
use App\Observers\LikeObserver;
use App\Policies\CommentPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PostPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);

        Follow::observe(FollowObserver::class);
        Like::observe(LikeObserver::class);
        Comment::observe(CommentObserver::class);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Policies/ app/Providers/AppServiceProvider.php
git commit -m "feat: add authorization policies and observer registration"
```

---

## Task 6: Auth Endpoints (TDD)

**Files:**
- Create: `tests/Feature/Auth/AuthTest.php`
- Create: `app/Http/Requests/Auth/RegisterRequest.php`
- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Resources/UserResource.php`
- Create: `app/Http/Controllers/AuthController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Auth/AuthTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'username', 'email']]);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_requires_unique_username(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->postJson('/api/v1/register', [
            'name' => 'Other',
            'username' => 'taken',
            'email' => 'other@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200)->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/logout')
            ->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test tests/Feature/Auth/AuthTest.php
```

Expected: FAIL — routes not found (404).

- [ ] **Step 3: Create RegisterRequest**

```bash
php artisan make:request Auth/RegisterRequest
```

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:30', 'unique:users,username', 'regex:/^[a-zA-Z0-9_.]+$/'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

- [ ] **Step 4: Create LoginRequest**

```bash
php artisan make:request Auth/LoginRequest
```

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 5: Create UserResource**

```bash
php artisan make:resource UserResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'username'         => $this->username,
            'email'            => $this->when($request->user()?->id === $this->id, $this->email),
            'bio'              => $this->bio,
            'avatar_url'       => $this->avatar ? \Storage::disk('s3')->temporaryUrl($this->avatar, now()->addHour()) : null,
            'followers_count'  => $this->whenLoaded('followers', fn() => $this->followers->count()),
            'following_count'  => $this->whenLoaded('following', fn() => $this->following->count()),
            'is_following'     => $request->user() ? $request->user()->isFollowing($this->resource) : false,
            'created_at'       => $this->created_at,
        ];
    }
}
```

- [ ] **Step 6: Create AuthController**

```bash
php artisan make:controller AuthController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
```

- [ ] **Step 7: Create routes/api.php**

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/users/{user}',            [UserController::class, 'show']);
Route::get('/users/{user}/followers',  [UserController::class, 'followers']);
Route::get('/users/{user}/following',  [UserController::class, 'following']);
Route::get('/posts/{post}',            [PostController::class, 'show']);
Route::get('/posts/{post}/comments',   [CommentController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::put('/users/{user}',         [UserController::class, 'update']);
    Route::post('/users/{user}/follow', [UserController::class, 'follow']);
    Route::delete('/users/{user}/follow', [UserController::class, 'unfollow']);

    Route::get('/feed',            [PostController::class, 'feed']);
    Route::post('/posts',          [PostController::class, 'store']);
    Route::put('/posts/{post}',    [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    Route::post('/posts/{post}/likes',   [LikeController::class, 'store']);
    Route::delete('/posts/{post}/likes', [LikeController::class, 'destroy']);

    Route::post('/posts/{post}/comments',  [CommentController::class, 'store']);
    Route::put('/comments/{comment}',      [CommentController::class, 'update']);
    Route::delete('/comments/{comment}',   [CommentController::class, 'destroy']);

    Route::get('/notifications',                       [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read',   [NotificationController::class, 'markRead']);
});
```

- [ ] **Step 8: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Auth/AuthTest.php
```

Expected: 5 tests, 5 passed.

- [ ] **Step 9: Commit**

```bash
git add app/Http/ routes/api.php tests/Feature/Auth/
git commit -m "feat: auth endpoints (register, login, logout) with TDD"
```

---

## Task 7: User Profile Endpoints (TDD)

**Files:**
- Create: `tests/Feature/Profile/ProfileTest.php`
- Create: `app/Http/Requests/User/UpdateUserRequest.php`
- Create: `app/Http/Controllers/UserController.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Profile/ProfileTest.php`:

```php
<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_profile(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'username']]);
    }

    public function test_owner_can_update_profile(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'New Name',
                'bio'  => 'My bio',
            ])->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'bio' => 'My bio']);
    }

    public function test_non_owner_cannot_update_profile(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/users/{$owner->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_can_upload_avatar(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/users/{$user->id}", [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])->assertStatus(200);

        $this->assertNotNull($user->fresh()->avatar);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test tests/Feature/Profile/ProfileTest.php
```

Expected: FAIL — UserController not found.

- [ ] **Step 3: Create UpdateUserRequest**

```bash
php artisan make:request User/UpdateUserRequest
```

```php
<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'bio'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'avatar'   => ['sometimes', 'image', 'max:5120'],
            'username' => ['sometimes', 'string', 'max:30', Rule::unique('users')->ignore($this->user()->id), 'regex:/^[a-zA-Z0-9_.]+$/'],
        ];
    }
}
```

- [ ] **Step 4: Create UserController**

```bash
php artisan make:controller UserController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function show(User $user): JsonResponse
    {
        $user->loadCount(['followers', 'following']);
        return response()->json(['data' => new UserResource($user)]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->only(['name', 'bio', 'username']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('s3')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store("avatars/{$user->id}", 's3');
        }

        $user->update($data);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function follow(User $user): JsonResponse
    {
        $follower = auth()->user();

        if ($follower->id === $user->id) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        if ($follower->isFollowing($user)) {
            return response()->json(['message' => 'Already following'], 422);
        }

        \App\Models\Follow::create([
            'follower_id'  => $follower->id,
            'following_id' => $user->id,
        ]);

        return response()->json(status: 204);
    }

    public function unfollow(User $user): JsonResponse
    {
        \App\Models\Follow::where('follower_id', auth()->id())
            ->where('following_id', $user->id)
            ->delete();

        return response()->json(status: 204);
    }

    public function followers(User $user): JsonResponse
    {
        $followers = $user->followers()->paginate(20);
        return response()->json(UserResource::collection($followers)->response()->getData(true));
    }

    public function following(User $user): JsonResponse
    {
        $following = $user->following()->paginate(20);
        return response()->json(UserResource::collection($following)->response()->getData(true));
    }
}
```

- [ ] **Step 5: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Profile/ProfileTest.php
```

Expected: 4 tests, 4 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/UserController.php app/Http/Requests/User/ tests/Feature/Profile/
git commit -m "feat: user profile endpoints (view, update, avatar upload)"
```

---

## Task 8: Follow System (TDD)

**Files:**
- Create: `tests/Feature/Social/FollowTest.php`

*(UserController already has follow/unfollow — this task tests it)*

- [ ] **Step 1: Write failing test** (Task 8)

Create `tests/Feature/Social/FollowTest.php`:

```php
<?php

namespace Tests\Feature\Social;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_another(): void
    {
        $follower = User::factory()->create();
        $target   = User::factory()->create();

        $this->actingAs($follower, 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/follow")
            ->assertStatus(204);

        $this->assertDatabaseHas('follows', [
            'follower_id'  => $follower->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_user_can_unfollow(): void
    {
        $follower = User::factory()->create();
        $target   = User::factory()->create();
        Follow::create(['follower_id' => $follower->id, 'following_id' => $target->id]);

        $this->actingAs($follower, 'sanctum')
            ->deleteJson("/api/v1/users/{$target->id}/follow")
            ->assertStatus(204);

        $this->assertDatabaseMissing('follows', ['follower_id' => $follower->id, 'following_id' => $target->id]);
    }

    public function test_user_cannot_follow_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/users/{$user->id}/follow")
            ->assertStatus(422);
    }

    public function test_duplicate_follow_returns_422(): void
    {
        $follower = User::factory()->create();
        $target   = User::factory()->create();
        Follow::create(['follower_id' => $follower->id, 'following_id' => $target->id]);

        $this->actingAs($follower, 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/follow")
            ->assertStatus(422);
    }

    public function test_can_list_followers(): void
    {
        $user      = User::factory()->create();
        $followers = User::factory()->count(3)->create();
        foreach ($followers as $f) {
            Follow::create(['follower_id' => $f->id, 'following_id' => $user->id]);
        }

        $this->getJson("/api/v1/users/{$user->id}/followers")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_list_following(): void
    {
        $user    = User::factory()->create();
        $targets = User::factory()->count(2)->create();
        foreach ($targets as $t) {
            Follow::create(['follower_id' => $user->id, 'following_id' => $t->id]);
        }

        $this->getJson("/api/v1/users/{$user->id}/following")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
```

- [ ] **Step 3: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Social/FollowTest.php
```

Expected: 6 tests, 6 passed.

- [ ] **Step 4: Commit**

```bash
git add app/Observers/ tests/Feature/Social/
git commit -m "feat: follow/unfollow system with tests"
```

---

## Task 9: Posts Endpoints (TDD)

**Files:**
- Create: `tests/Feature/Content/PostTest.php`
- Create: `app/Http/Requests/Post/StorePostRequest.php`
- Create: `app/Http/Requests/Post/UpdatePostRequest.php`
- Create: `app/Http/Resources/PostResource.php`
- Create: `app/Http/Controllers/PostController.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Content/PostTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_post(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/posts', [
                'caption' => 'Hello world',
                'image'   => UploadedFile::fake()->image('photo.jpg'),
            ])->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'caption', 'image_url', 'user']]);
    }

    public function test_post_requires_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/posts', ['caption' => 'No image'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_anyone_can_view_post(): void
    {
        $post = Post::factory()->create();

        $this->getJson("/api/v1/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'caption']]);
    }

    public function test_owner_can_update_post(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/posts/{$post->id}", ['caption' => 'Updated'])
            ->assertStatus(200)
            ->assertJsonPath('data.caption', 'Updated');
    }

    public function test_non_owner_cannot_update_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post  = Post::factory()->for($owner)->create();

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/posts/{$post->id}", ['caption' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_owner_can_delete_post(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/posts/{$post->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test tests/Feature/Content/PostTest.php
```

Expected: FAIL — PostController not found, Post factory not found.

- [ ] **Step 3: Create Post factory**

```bash
php artisan make:factory PostFactory --model=Post
```

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'caption'    => fake()->sentence(),
            'image_path' => 'posts/fake-image.jpg',
        ];
    }
}
```

- [ ] **Step 4: Create StorePostRequest**

```bash
php artisan make:request Post/StorePostRequest
```

```php
<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:2200'],
            'image'   => ['required', 'image', 'max:10240'],
        ];
    }
}
```

- [ ] **Step 5: Create UpdatePostRequest**

```bash
php artisan make:request Post/UpdatePostRequest
```

```php
<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'caption' => ['sometimes', 'nullable', 'string', 'max:2200'],
        ];
    }
}
```

- [ ] **Step 6: Create PostResource**

```bash
php artisan make:resource PostResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'caption'        => $this->caption,
            'image_url'      => Storage::disk('s3')->temporaryUrl($this->image_path, now()->addHour()),
            'likes_count'    => $this->likes_count,
            'comments_count' => $this->comments_count,
            'liked_by_me'    => $this->whenLoaded('likes', fn() => $this->likes->isNotEmpty()),
            'user'           => new UserResource($this->whenLoaded('user')),
            'created_at'     => $this->created_at,
        ];
    }
}
```

- [ ] **Step 7: Create PostController**

```bash
php artisan make:controller PostController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function __construct(private FeedService $feedService) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $imagePath = $request->file('image')->store("posts/{$request->user()->id}", 's3');

        $post = Post::create([
            'user_id'    => $request->user()->id,
            'caption'    => $request->caption,
            'image_path' => $imagePath,
        ]);

        return response()->json(['data' => new PostResource($post->load('user'))], 201);
    }

    public function show(Post $post): JsonResponse
    {
        $post->load('user');
        return response()->json(['data' => new PostResource($post)]);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('modify', $post);
        $post->update($request->only(['caption']));
        return response()->json(['data' => new PostResource($post->load('user'))]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('modify', $post);
        Storage::disk('s3')->delete($post->image_path);
        $post->delete();
        return response()->json(status: 204);
    }

    public function feed(): JsonResponse
    {
        $posts = $this->feedService->getFeed(auth()->user());
        return response()->json(PostResource::collection($posts)->response()->getData(true));
    }
}
```

- [ ] **Step 8: Create placeholder FeedService** (needed for PostController injection)

```bash
php artisan make:class Services/FeedService
```

```php
<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

class FeedService
{
    public function getFeed(User $user, int $perPage = 15): CursorPaginator
    {
        return Post::whereIn('user_id', $user->following()->pluck('users.id'))
            ->with([
                'user:id,name,username,avatar',
                'likes' => fn($q) => $q->where('user_id', $user->id),
            ])
            ->latest()
            ->cursorPaginate($perPage);
    }
}
```

- [ ] **Step 9: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Content/PostTest.php
```

Expected: 6 tests, 6 passed.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/PostController.php app/Http/Requests/Post/ app/Http/Resources/PostResource.php app/Services/FeedService.php database/factories/PostFactory.php tests/Feature/Content/PostTest.php
git commit -m "feat: posts CRUD endpoints with image upload (TDD)"
```

---

## Task 10: Likes Endpoints (TDD)

**Files:**
- Create: `tests/Feature/Content/LikeTest.php`
- Create: `app/Http/Controllers/LikeController.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Content/LikeTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_like_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/likes")
            ->assertStatus(204);

        $this->assertDatabaseHas('likes', ['user_id' => $user->id, 'post_id' => $post->id]);
        $this->assertEquals(1, $post->fresh()->likes_count);
    }

    public function test_user_cannot_like_same_post_twice(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Like::create(['user_id' => $user->id, 'post_id' => $post->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/likes")
            ->assertStatus(422);
    }

    public function test_user_can_unlike_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['likes_count' => 1]);
        Like::create(['user_id' => $user->id, 'post_id' => $post->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/posts/{$post->id}/likes")
            ->assertStatus(204);

        $this->assertDatabaseMissing('likes', ['user_id' => $user->id, 'post_id' => $post->id]);
        $this->assertEquals(0, $post->fresh()->likes_count);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test tests/Feature/Content/LikeTest.php
```

Expected: FAIL — LikeController not found.

- [ ] **Step 3: Create LikeController**

```bash
php artisan make:controller LikeController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class LikeController extends Controller
{
    public function store(Post $post): JsonResponse
    {
        $user = auth()->user();

        if (Like::where('user_id', $user->id)->where('post_id', $post->id)->exists()) {
            return response()->json(['message' => 'Already liked'], 422);
        }

        Like::create(['user_id' => $user->id, 'post_id' => $post->id]);
        $post->increment('likes_count');

        return response()->json(status: 204);
    }

    public function destroy(Post $post): JsonResponse
    {
        $deleted = Like::where('user_id', auth()->id())
            ->where('post_id', $post->id)
            ->delete();

        if ($deleted) {
            $post->decrement('likes_count');
        }

        return response()->json(status: 204);
    }
}
```

- [ ] **Step 4: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Content/LikeTest.php
```

Expected: 3 tests, 3 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/LikeController.php tests/Feature/Content/LikeTest.php
git commit -m "feat: like/unlike post endpoints (TDD)"
```

---

## Task 11: Comments Endpoints (TDD)

**Files:**
- Create: `tests/Feature/Content/CommentTest.php`
- Create: `app/Http/Requests/Comment/StoreCommentRequest.php`
- Create: `app/Http/Requests/Comment/UpdateCommentRequest.php`
- Create: `app/Http/Resources/CommentResource.php`
- Create: `app/Http/Controllers/CommentController.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Content/CommentTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_comments(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->count(3)->for($post)->create();

        $this->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_authenticated_user_can_comment(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Nice post!'])
            ->assertStatus(201)
            ->assertJsonPath('data.body', 'Nice post!');

        $this->assertEquals(1, $post->fresh()->comments_count);
    }

    public function test_owner_can_update_comment(): void
    {
        $user    = User::factory()->create();
        $post    = Post::factory()->create();
        $comment = Comment::factory()->for($user)->for($post)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/comments/{$comment->id}", ['body' => 'Edited!'])
            ->assertStatus(200)
            ->assertJsonPath('data.body', 'Edited!');
    }

    public function test_non_owner_cannot_update_comment(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $post    = Post::factory()->create();
        $comment = Comment::factory()->for($owner)->for($post)->create();

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/comments/{$comment->id}", ['body' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_owner_can_delete_comment(): void
    {
        $user    = User::factory()->create();
        $post    = Post::factory()->create(['comments_count' => 1]);
        $comment = Comment::factory()->for($user)->for($post)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/comments/{$comment->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertEquals(0, $post->fresh()->comments_count);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test tests/Feature/Content/CommentTest.php
```

Expected: FAIL — CommentController not found, Comment factory not found.

- [ ] **Step 3: Create Comment factory**

```bash
php artisan make:factory CommentFactory --model=Comment
```

```php
<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'body'    => fake()->sentence(),
        ];
    }
}
```

- [ ] **Step 4: Create StoreCommentRequest**

```bash
php artisan make:request Comment/StoreCommentRequest
```

```php
<?php

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Create UpdateCommentRequest**

```bash
php artisan make:request Comment/UpdateCommentRequest
```

```php
<?php

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 6: Create CommentResource**

```bash
php artisan make:resource CommentResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'user'       => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
        ];
    }
}
```

- [ ] **Step 7: Create CommentController**

```bash
php artisan make:controller CommentController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function index(Post $post): JsonResponse
    {
        $comments = $post->comments()->with('user:id,name,username,avatar')->latest()->paginate(20);
        return response()->json(CommentResource::collection($comments)->response()->getData(true));
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'body'    => $request->body,
        ]);

        $post->increment('comments_count');

        return response()->json(['data' => new CommentResource($comment->load('user'))], 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('modify', $comment);
        $comment->update(['body' => $request->body]);
        return response()->json(['data' => new CommentResource($comment->load('user'))]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('modify', $comment);
        $comment->post->decrement('comments_count');
        $comment->delete();
        return response()->json(status: 204);
    }
}
```

- [ ] **Step 8: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Content/CommentTest.php
```

Expected: 5 tests, 5 passed.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/CommentController.php app/Http/Requests/Comment/ app/Http/Resources/CommentResource.php database/factories/CommentFactory.php tests/Feature/Content/CommentTest.php
git commit -m "feat: comments CRUD endpoints (TDD)"
```

---

## Task 12: Feed (TDD)

**Files:**
- Create: `tests/Feature/Feed/FeedTest.php`

*(FeedService already created in Task 9 — this task tests it)*

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Feed/FeedTest.php`:

```php
<?php

namespace Tests\Feature\Feed;

use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_shows_posts_from_followed_users(): void
    {
        $user    = User::factory()->create();
        $followed = User::factory()->create();
        $other   = User::factory()->create();

        Follow::create(['follower_id' => $user->id, 'following_id' => $followed->id]);

        $followedPost = Post::factory()->for($followed)->create();
        $otherPost    = Post::factory()->for($other)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/feed')
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($followedPost->id));
        $this->assertFalse($ids->contains($otherPost->id));
    }

    public function test_feed_is_chronological_newest_first(): void
    {
        $user    = User::factory()->create();
        $followed = User::factory()->create();
        Follow::create(['follower_id' => $user->id, 'following_id' => $followed->id]);

        $older = Post::factory()->for($followed)->create(['created_at' => now()->subDay()]);
        $newer = Post::factory()->for($followed)->create(['created_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/feed')
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->values();
        $this->assertEquals($newer->id, $ids[0]);
        $this->assertEquals($older->id, $ids[1]);
    }

    public function test_empty_feed_when_following_nobody(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/feed')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/v1/feed')->assertStatus(401);
    }
}
```

- [ ] **Step 2: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Feed/FeedTest.php
```

Expected: 4 tests, 4 passed.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Feed/
git commit -m "feat: feed endpoint with FeedService (TDD)"
```

---

## Task 13: Notifications + Observers (TDD)

**Files:**
- Create: `tests/Feature/Notification/NotificationTest.php`
- Create: `app/Http/Resources/NotificationResource.php`
- Create: `app/Http/Controllers/NotificationController.php`
- Modify: `app/Services/NotificationService.php` (create)
- Modify: `app/Observers/FollowObserver.php`
- Modify: `app/Observers/LikeObserver.php`
- Modify: `app/Observers/CommentObserver.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Notification/NotificationTest.php`:

```php
<?php

namespace Tests\Feature\Notification;

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_creates_notification_for_post_owner(): void
    {
        $owner  = User::factory()->create();
        $liker  = User::factory()->create();
        $post   = Post::factory()->for($owner)->create();

        $this->actingAs($liker, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/likes");

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type'    => 'like',
        ]);
    }

    public function test_comment_creates_notification_for_post_owner(): void
    {
        $owner    = User::factory()->create();
        $commenter = User::factory()->create();
        $post     = Post::factory()->for($owner)->create();

        $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Nice!']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type'    => 'comment',
        ]);
    }

    public function test_follow_creates_notification(): void
    {
        $target   = User::factory()->create();
        $follower = User::factory()->create();

        $this->actingAs($follower, 'sanctum')
            ->postJson("/api/v1/users/{$target->id}/follow");

        $this->assertDatabaseHas('notifications', [
            'user_id' => $target->id,
            'type'    => 'follow',
        ]);
    }

    public function test_no_self_notification_on_own_post_like(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/likes");

        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id, 'type' => 'like']);
    }

    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user         = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(200);

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test tests/Feature/Notification/NotificationTest.php
```

Expected: FAIL — NotificationController not found, Notification factory not found.

- [ ] **Step 3: Create Notification factory**

```bash
php artisan make:factory NotificationFactory --model=Notification
```

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type'    => fake()->randomElement(['like', 'comment', 'follow']),
            'data'    => ['actor_id' => 1, 'actor_username' => 'someuser'],
            'read_at' => null,
        ];
    }
}
```

- [ ] **Step 4: Create NotificationService**

```bash
php artisan make:class Services/NotificationService
```

```php
<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function notify(User $recipient, string $type, array $data): Notification
    {
        return Notification::create([
            'user_id' => $recipient->id,
            'type'    => $type,
            'data'    => $data,
        ]);
    }
}
```

- [ ] **Step 5: Implement FollowObserver**

Replace `app/Observers/FollowObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Follow;
use App\Services\NotificationService;

class FollowObserver
{
    public function __construct(private NotificationService $notificationService) {}

    public function created(Follow $follow): void
    {
        if ($follow->follower_id === $follow->following_id) {
            return;
        }

        $this->notificationService->notify(
            $follow->following,
            'follow',
            ['actor_id' => $follow->follower_id, 'actor_username' => $follow->follower->username]
        );
    }
}
```

- [ ] **Step 6: Implement LikeObserver**

Replace `app/Observers/LikeObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Like;
use App\Services\NotificationService;

class LikeObserver
{
    public function __construct(private NotificationService $notificationService) {}

    public function created(Like $like): void
    {
        if ($like->user_id === $like->post->user_id) {
            return;
        }

        $this->notificationService->notify(
            $like->post->user,
            'like',
            [
                'actor_id'       => $like->user_id,
                'actor_username' => $like->user->username,
                'post_id'        => $like->post_id,
            ]
        );
    }
}
```

- [ ] **Step 7: Implement CommentObserver**

Replace `app/Observers/CommentObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Comment;
use App\Services\NotificationService;

class CommentObserver
{
    public function __construct(private NotificationService $notificationService) {}

    public function created(Comment $comment): void
    {
        if ($comment->user_id === $comment->post->user_id) {
            return;
        }

        $this->notificationService->notify(
            $comment->post->user,
            'comment',
            [
                'actor_id'       => $comment->user_id,
                'actor_username' => $comment->user->username,
                'post_id'        => $comment->post_id,
                'comment_id'     => $comment->id,
            ]
        );
    }
}
```

- [ ] **Step 8: Create NotificationResource**

```bash
php artisan make:resource NotificationResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'data'       => $this->data,
            'read_at'    => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
```

- [ ] **Step 9: Create NotificationController**

```bash
php artisan make:controller NotificationController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json(NotificationResource::collection($notifications)->response()->getData(true));
    }

    public function markRead(Notification $notification): JsonResponse
    {
        $this->authorize('own', $notification);
        $notification->update(['read_at' => now()]);
        return response()->json(['data' => new NotificationResource($notification)]);
    }
}
```

- [ ] **Step 10: Run tests — verify they pass**

```bash
php artisan test tests/Feature/Notification/NotificationTest.php
```

Expected: 6 tests, 6 passed.

- [ ] **Step 11: Run full test suite**

```bash
php artisan test
```

Expected: all tests green.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/NotificationController.php app/Http/Resources/NotificationResource.php app/Services/NotificationService.php app/Observers/ database/factories/NotificationFactory.php tests/Feature/Notification/
git commit -m "feat: notifications system with observers (like/comment/follow) TDD"
```

---

## Task 14: Mercure SSE Integration

**Files:**
- Modify: `app/Services/NotificationService.php`
- Modify: `docker/Caddyfile` (already configured in Task 1)

- [ ] **Step 1: Update NotificationService to publish Mercure events**

Replace `app/Services/NotificationService.php`:

```php
<?php

namespace App\Services;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notify(User $recipient, string $type, array $data): Notification
    {
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'type'    => $type,
            'data'    => $data,
        ]);

        $this->publishToMercure($recipient, $notification);

        return $notification;
    }

    private function publishToMercure(User $recipient, Notification $notification): void
    {
        $mercureUrl = config('mercure.url');
        $secret     = config('mercure.jwt_secret');

        if (!$mercureUrl || !$secret) {
            return;
        }

        $jwt = JWT::encode([
            'mercure' => ['publish' => ["users/{$recipient->id}/notifications"]],
        ], $secret, 'HS256');

        try {
            Http::withToken($jwt)
                ->asForm()
                ->post($mercureUrl, [
                    'topic' => "users/{$recipient->id}/notifications",
                    'data'  => json_encode(new NotificationResource($notification)),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Mercure publish failed: ' . $e->getMessage());
        }
    }
}
```

- [ ] **Step 2: Run full test suite — verify nothing broke**

```bash
php artisan test
```

Expected: all tests green (Mercure publish failure is caught and logged, does not affect tests).

- [ ] **Step 3: Commit**

```bash
git add app/Services/NotificationService.php
git commit -m "feat: publish real-time notifications to Mercure SSE hub"
```

- [ ] **Step 4: Verify Docker build**

```bash
docker compose build --no-cache
```

Expected: build succeeds, no errors.

- [ ] **Step 5: Start services and test end-to-end**

```bash
docker compose up -d
# Wait for healthchecks
docker compose ps
# Generate app key if not set
docker compose exec app php artisan key:generate
# Verify migrations ran
docker compose exec app php artisan migrate:status
# Test register endpoint
curl -s -X POST http://localhost/api/v1/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Test","username":"testuser","email":"test@example.com","password":"password123","password_confirmation":"password123"}' \
  | jq .
```

Expected: `{"token":"...","user":{...}}`.

- [ ] **Step 6: Final commit**

```bash
git add .
git commit -m "chore: finalize InstaClone API — all features complete"
```

---

## Summary

| Task | Feature | Tests |
|------|---------|-------|
| 1 | Docker (FrankenPHP + MySQL + MinIO) | — |
| 2 | Laravel 11 + Sanctum + S3 | — |
| 3 | Database migrations | — |
| 4 | Eloquent models | — |
| 5 | Policies + ServiceProvider | — |
| 6 | Auth (register/login/logout) | 5 |
| 7 | User profile (view/update/avatar) | 4 |
| 8 | Follow/unfollow system | 6 |
| 9 | Posts CRUD + image upload | 6 |
| 10 | Likes | 3 |
| 11 | Comments CRUD | 5 |
| 12 | Feed (FeedService) | 4 |
| 13 | Notifications + Observers | 6 |
| 14 | Mercure SSE integration | — |

**Total: 39 feature tests**
