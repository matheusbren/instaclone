# InstaClone - Frontend (Vue 3)

## Visao Geral

Este diretorio contem a SPA do InstaClone, escrita em Vue 3 com Vite. O app e dividido entre uma area autenticada e uma area de visitante, consome a API via Bearer token, persiste apenas o `access_token` no `localStorage` e reidrata o usuario atual com `GET /auth/me` ao entrar nas rotas protegidas.

Hoje o frontend ja entrega os fluxos de:

- autenticacao (`/login`, `/cadastro`)
- feed (`/feed`)
- descoberta de perfis (`/descobrir`)
- criacao de post (`/criar`)
- perfil proprio e de terceiros (`/perfil` e `?user=<username>`)
- edicao de perfil (`/perfil/editar`)
- listas de seguidores/seguindo (`/perfil/lista/:type`)
- detalhes do post (`/posts/:postId`)
- fallback 404

## Stack

- Vue 3 com Composition API e `<script setup>`
- Vue Router 4 com guards `requiresAuth` e `requiresGuest`
- Pinia para os stores `auth` e `feed`
- axios com interceptor de token Bearer e limpeza de sessao em `401`
- Bootstrap 5 + tema proprio em `src/assets/styles/theme.css`
- Vite 8

`package.json` exige Node `^20.19.0 || >=22.12.0`.

## Estrutura

```text
src/
  assets/styles/   tema global
  components/      UI reutilizavel (layout, feed, profile)
  composables/     useAuth e useFeed
  layouts/         AppLayout e AuthLayout
  router/          rotas e guards
  services/        cliente axios + wrappers por recurso
  stores/          auth, feed e helpers de normalizacao
  views/           telas de auth, app e 404
```

## Comportamento Atual

### Autenticacao

- `POST /auth/login` e `POST /auth/register` criam sessao e atualizam o store `auth`
- `POST /auth/logout` limpa a sessao local mesmo se o token ja estiver invalido
- `GET /auth/me` hidrata o usuario autenticado a partir do token salvo
- o router redireciona visitantes para `/login` e usuarios autenticados para `/feed`

O modulo `auth.service.js` tambem expoe `refresh()`, mas a UI atual nao usa esse endpoint.

### Layout Principal

O shell autenticado usa:

- navegacao inferior no mobile e lateral no desktop
- quatro entradas principais: `Home`, `Descobrir`, `Criar` e `Perfil`
- `AppShell` com slots `sidebar`, `header`, `default` e `footer`
- `<RouterView v-slot="{ Component }">` com `<component :is="Component" />` no `AppLayout`

### Feed

- carrega posts com `GET /feed` usando cursor pagination (`next_cursor`)
- o store `feed` normaliza os posts e centraliza `fetchFeed`, `loadMoreFeed`, `toggleLike`, `addComment` e `createPost`
- cada card mostra autor, imagem, legenda, data, total de curtidas e total de comentarios
- comentarios inline sao enviados por `POST /posts/:id/comments`
- curtidas usam `POST /posts/:id/like` e `DELETE /posts/:id/unlike`

### Descobrir

- a tela usa `GET /users/suggestions` para listar perfis sugeridos
- o estado de relacionamento do viewer e montado a partir de `GET /users/:id/following`
- seguir/deixar de seguir usa `POST /users/:id/follow` e `DELETE /users/:id/unfollow`

### Criar Post

- aceita `image/jpeg`, `image/jpg`, `image/png` e `image/webp`
- limita upload a 5 MB
- exige imagem e legenda antes do envio
- usa `URL.createObjectURL` para preview local e revoga o blob ao limpar ou sair da tela
- envia `FormData` para `POST /posts`
- aplica limite de `2200` caracteres para a legenda

### Perfil

- o perfil alvo e buscado por username com `GET /users/{username}`
- perfis de terceiros sao acessados com `?user=<username>`
- a pagina carrega em paralelo:
  - `GET /users/{id}/posts`
  - `GET /users/{id}/followers`
  - `GET /users/{id}/following`
- para perfis de terceiros, o estado do botao vem de `GET /users/{id}/is-following`
- o proprio perfil pode ser editado em `/perfil/editar`

### Editar Perfil

- `PUT /users/me` atualiza `name`, `username` e `bio`
- `POST /users/me/avatar` envia avatar em `multipart/form-data`
- limites usados na UI:
  - `name`: 255 caracteres
  - `username`: 30 caracteres
  - `bio`: 500 caracteres
  - avatar: 2 MB
- `username` aceita apenas letras, numeros, ponto e sublinhado

### Seguidores e Seguindo

- `/perfil/lista/seguidores` e `/perfil/lista/seguindo` usam paginacao por pagina
- a tela reaproveita `GET /users/{id}/followers` e `GET /users/{id}/following`
- o viewer pode seguir ou deixar de seguir perfis direto da lista

### Detalhes do Post

- busca o post com `GET /posts/:id`
- carrega comentarios com `GET /posts/:id/comments`
- adiciona comentarios com `POST /posts/:id/comments`
- o dono do comentario pode apagar via `DELETE /comments/:id`
- o dono do post pode apagar via `DELETE /posts/:id`

## Servicos Ja Prontos Sem Tela Dedicada

O frontend tambem ja possui wrappers de API que ainda nao estao ligados a uma view propria:

- `src/services/notifications.service.js`
- `users.search()`
- `posts.update()`
- `comments.update()`
- `likes.likers()`

## Configuracao Local

```bash
cp .env.example .env
npm ci
npm run dev
```

`.env.example` contem:

```bash
VITE_API_URL=http://localhost:8000/api
```

Para build local:

```bash
npm run build
npm run preview
```

## Docker

O frontend possui containerizacao pronta para producao:

1. `Dockerfile` multi-stage com build em `node:22-alpine`
2. runtime em `nginx:1.27-alpine`
3. `docker/nginx.conf` com fallback para `index.html` no history mode do Vue Router
4. `compose.yaml` expondo `3000:80`
5. `.dockerignore` excluindo artefatos locais e preservando `.env.example`

O build injeta `VITE_API_URL` como build-arg:

```bash
docker compose up -d --build
```
