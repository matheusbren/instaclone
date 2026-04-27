# InstaClone — Backend API

REST API para rede social inspirada no Instagram. Construída com Laravel 11 + FrankenPHP + MySQL, rodando via Docker.

## Stack

- **PHP 8.3** com **Laravel 11**
- **FrankenPHP** (servidor HTTP baseado em Caddy)
- **MySQL 8**
- **Laravel Sanctum** para autenticação por token
- **MinIO** (opcional) para armazenamento de arquivos S3-compatível

## Requisitos

- Docker e Docker Compose

## Como rodar

```bash
# Clone o repositório
git clone https://github.com/matheusbren/instaclone.git
cd instaclone

# Configure o .env
cp .env.example .env  # edite APP_KEY e credenciais se necessário

# Suba os containers
docker compose up -d --build
```

API disponível em `http://localhost:8000`.  
Frontend disponível em `http://localhost:3000`.

As migrations rodam automaticamente na inicialização do container.

## Variáveis de ambiente (.env)

| Variável | Descrição |
|---|---|
| `APP_KEY` | Chave de criptografia do Laravel |
| `DB_DATABASE` | Nome do banco (padrão: `instaclone`) |
| `DB_USERNAME` | Usuário do banco (padrão: `instaclone`) |
| `DB_PASSWORD` | Senha do banco (padrão: `secret`) |
| `FILESYSTEM_DISK` | Disk de arquivos (`public` para local, `s3` para MinIO) |

## Endpoints

### Auth
| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/auth/register` | Cadastro |
| POST | `/api/auth/login` | Login |
| POST | `/api/auth/logout` | Logout |
| POST | `/api/auth/refresh` | Renovar token |
| GET | `/api/auth/me` | Usuário autenticado |

### Usuários
| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/users/{username}` | Perfil por username |
| PUT | `/api/users/me` | Editar perfil |
| POST | `/api/users/me/avatar` | Atualizar avatar |
| GET | `/api/users/search?q=` | Buscar usuários |
| GET | `/api/users/suggestions` | Sugestões de quem seguir |
| GET | `/api/users/{user}/posts` | Posts do usuário |
| POST | `/api/users/{user}/follow` | Seguir |
| DELETE | `/api/users/{user}/follow` | Deixar de seguir |
| GET | `/api/users/{user}/followers` | Seguidores |
| GET | `/api/users/{user}/following` | Seguindo |
| GET | `/api/users/{user}/is-following` | Verificar se segue |

### Posts
| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/feed` | Feed paginado por cursor |
| POST | `/api/posts` | Criar post (multipart) |
| GET | `/api/posts/{post}` | Ver post |
| PUT | `/api/posts/{post}` | Editar legenda |
| DELETE | `/api/posts/{post}` | Deletar post |

### Curtidas
| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/posts/{post}/like` | Curtir |
| DELETE | `/api/posts/{post}/like` | Descurtir |
| GET | `/api/posts/{post}/likers` | Quem curtiu |

### Comentários
| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/posts/{post}/comments` | Listar comentários |
| POST | `/api/posts/{post}/comments` | Comentar |
| PUT | `/api/comments/{comment}` | Editar comentário |
| DELETE | `/api/comments/{comment}` | Deletar comentário |

## Frontend

O frontend (Vue 3) está na branch `frontend` deste repositório.

```bash
git checkout frontend
```
