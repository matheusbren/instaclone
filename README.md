# 📸 InstaClone — Frontend (Vue.js 3)

## Visão Geral

O InstaClone é uma rede social inspirada no Instagram, construída como projeto final da disciplina. Este repositório contém o frontend do projeto: uma SPA (Single Page Application) construída com Vue.js 3, Vue Router, Pinia e axios, totalmente integrada com a API Laravel/Sanctum descrita no diretório `backend/`.

Toda a persistência é feita pelo backend. O frontend mantém apenas o token de acesso no `localStorage` para hidratar a sessão entre recargas.

## Stack

- **Vue 3** com `<script setup>` e Composition API.
- **Vue Router** com guards de rota (`requiresAuth`, `requiresGuest`).
- **Pinia** para estado global (`auth`, `feed`).
- **axios** como cliente HTTP, com interceptors de token Bearer e tratamento de 401.
- **Bootstrap 5** + `theme.css` próprio para estilos base.
- **Vite** para build e dev server.

## Estrutura

```
src/
  assets/styles/         tema global e overrides
  components/            componentes reutilizáveis (feed, layout, profile)
  composables/           hooks finos sobre os stores (useAuth, useFeed)
  layouts/               AppLayout (área autenticada) e AuthLayout
  router/                definição de rotas e guards
  services/              cliente axios + wrappers por recurso (auth, users, posts, feed, follows, likes, comments, notifications — os dois últimos ficam prontos para consumo mesmo sem tela dedicada)
  stores/                stores Pinia + helpers de normalização
  views/                 páginas (auth/*, app/*, NotFoundView)
```

## Integração com o backend

O cliente HTTP (`src/services/api.js`) é uma instância do axios apontando para `VITE_API_URL` (default `http://localhost:8000/api`). Um interceptor de requisição injeta o token Bearer vindo do store `auth`; um interceptor de resposta limpa a sessão ao receber `401 Unauthorized`. Cada recurso tem um módulo dedicado em `src/services/*.service.js` que encapsula as rotas correspondentes da API.

### Configuração

```bash
cp .env.example .env
# ajuste VITE_API_URL se a API não estiver em http://localhost:8000/api
npm install
npm run dev
```

Para build de produção:

```bash
npm run build
npm run preview
```

## Autenticação (Sanctum)

O fluxo de login e cadastro bate direto nos endpoints `POST /api/auth/login` e `POST /api/auth/register`. O `access_token` retornado é persistido em `localStorage` e injetado automaticamente em todas as requisições autenticadas. Ao entrar, a SPA redireciona para o feed; acessos a rotas protegidas sem sessão ativa são redirecionados para a tela de login. O botão "Sair" dispara `POST /api/auth/logout`, invalidando o token no backend e limpando o armazenamento local. Ao carregar a página, o router usa `GET /api/auth/me` para hidratar o usuário atual a partir do token salvo.

## Layout Principal

A navegação é feita por uma barra inferior (mobile) ou lateral (desktop) com links para Home, Criar Post e Perfil. O layout base utiliza slots para áreas de conteúdo dinâmico (header, main, footer) e componentes dinâmicos (`<component :is>`) para troca de views.

## Feed

O feed exibe os posts das pessoas que o usuário segue, carregados via `GET /api/feed` (cursor pagination). Cada post traz autor, imagem, legenda, data, contadores de curtidas/comentários e a flag `liked_by_me`. Curtidas (`POST /api/posts/:id/like`, `DELETE /api/posts/:id/unlike`), comentários inline (`POST /api/posts/:id/comments`) e paginação com cursor estão totalmente conectados ao backend; o store atualiza o estado local com a resposta da API para manter a UI consistente.

## Criar Post

A tela de criação envia um `FormData` com `image` (File) e `caption` para `POST /api/posts`. O preview é gerado com `URL.createObjectURL` e revogado quando necessário. Em caso de sucesso, a nova publicação é prependida no feed do store.

## Perfil

A área de perfil faz `GET /api/users/{username}` para o usuário alvo, e em paralelo `GET /api/users/{id}/posts`, `GET /api/users/{id}/followers`, `GET /api/users/{id}/following` para preencher grade e contadores. Para perfis alheios, `GET /api/users/{id}/is-following` determina o estado do botão de follow, que dispara `POST /api/users/{id}/follow` ou `DELETE /api/users/{id}/unfollow` conforme o caso.

A edição do próprio perfil (`PUT /api/users/me`) cobre nome, username e bio. O upload de avatar vai como `multipart/form-data` para `POST /api/users/me/avatar`. O usuário autenticado no store é atualizado com o retorno da API.

As listas de seguidores e seguindo têm sua própria tela paginada, com botão "Seguir/Seguindo" respeitando o relacionamento atual do viewer.

## Detalhes do Post

A tela individual busca o post por `GET /api/posts/:id` e pagina comentários via `GET /api/posts/:id/comments` com botão "carregar mais". Campo de comentário envia para `POST /api/posts/:id/comments`; o dono de cada comentário pode apagá-lo via `DELETE /api/comments/:id`. Quando o usuário logado é o autor do post, a ação "Deletar post" chama `DELETE /api/posts/:id` e retorna ao perfil.

## Dockerização

O frontend é containerizado com build multi-stage:

1. **`builder`** (`node:22-alpine`): roda `npm ci` com cache e `npm run build` com `VITE_API_URL` injetado via build-arg.
2. **`runtime`** (`nginx:1.27-alpine`): serve o `dist/` com a config customizada de `docker/nginx.conf`, que faz fallback para `index.html` (history mode do Vue Router) e aplica cache para estáticos.

O `compose.yaml` expõe a porta `3000` do host mapeada para a `80` do container e aceita `VITE_API_URL` como variável de ambiente.

```bash
# Primeiro suba o backend em ../backend
cd ../backend && docker compose up -d --build

# Depois o frontend
cd ../frontend
docker compose up -d --build
```

A SPA fica acessível em `http://localhost:3000` e conversa com a API em `http://localhost:8000/api`.
