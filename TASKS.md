# 📸 InstaClone (frontend) — Organização de Tasks

### Frontend (UI do insta-clone)

### 1 - Setup do Projeto
- [x] Inicializar projeto (Vue)
- [x] Configurar estrutura de pastas
- [x] Configurar rotas do frontend (Vue Router)
- [x] Configurar gerenciamento de estado compartilhado com Pinia
- [x] Definir tema global (cores, fontes, espaçamentos no estilo Instagram)
- [x] Configurar axios (cliente + interceptors de token/401)

### 2 - Autenticação
- [x] Tela de Login
- [x] Tela de Cadastro
- [x] Centralizar sessão autenticada em store global
- [x] Lógica de salvar/remover token no localStorage
- [x] Redirecionamento automático (logado → feed, deslogado → login)
- [x] Guard de rotas protegidas
- [x] Configurar autenticação com backend (Sanctum: /auth/login, /auth/register, /auth/logout, /auth/me)

### 3 - Layout Principal
- [x] Navbar inferior (mobile) ou lateral (desktop) — Home, Criar e Perfil
- [x] Layout responsivo base (mobile-first)
- [x] Usar slots no layout base para áreas de conteúdo dinâmico (header, main, footer)
- [x] Usar componentes dinâmicos (`<component :is>`) para navegação ou troca de views

### 4 - Feed
- [x] Componente de Post (imagem, legenda, likes, comentários, data)
- [x] Listagem do feed via GET /api/feed (cursor pagination)
- [x] Botão "carregar mais" consumindo next_cursor
- [x] Store Pinia como fonte reativa do feed
- [x] Ação de curtir/descurtir integrada à API
- [x] Ação de comentar inline integrada à API
- [x] Link pro perfil do autor

### 5 - Criar Post
- [x] Tela de upload de imagem (File real, multipart)
- [x] Preview da imagem com URL.createObjectURL
- [x] Campo de legenda com contador
- [x] Botão de publicar (POST /api/posts)
- [x] Feedback de sucesso/erro com mensagens da API

### 6 - Perfil
- [x] Tela de perfil (foto, bio, contadores de posts/seguidores/seguindo)
- [x] Grid de posts do usuário (GET /api/users/{id}/posts)
- [x] Botão seguir/deixar de seguir (POST /follow, DELETE /unfollow, GET /is-following)
- [x] Botão editar perfil (perfil próprio)
- [x] Tela de editar perfil (nome, username, bio, avatar)
- [x] Upload de avatar (POST /api/users/me/avatar)
- [x] Lista paginada de seguidores
- [x] Lista paginada de seguindo

### 7 - Detalhes do Post
- [x] Tela individual do post (GET /api/posts/{id})
- [x] Listagem de comentários paginada com botão "carregar mais"
- [x] Campo pra adicionar comentário
- [x] Contagem de curtidas e comentários vindo do backend
- [x] Deletar comentário (dono)
- [x] Botão de deletar post (dono)

### 8 - Dockerização
- [x] Dockerfile multi-stage (node:22-alpine build + nginx:1.27-alpine runtime)
- [x] `docker/nginx.conf` com fallback para index.html (history mode)
- [x] `compose.yaml` expondo porta 3000 e aceitando VITE_API_URL como build-arg/env
- [x] `.dockerignore` excluindo node_modules, dist, logs e .env
- [x] Validar build de produção (`npm run build`) e imagem Docker

### 9 - Testes
- [x] Testes de todos os casos da aplicação utilizando DevTools MCP (chrome)
  - [x] Fluxo de cadastro (inclui validação de username duplicado)
  - [x] Fluxo de login (inclui erro com credenciais inválidas)
  - [x] Logout redireciona para /login sem ficar preso no feed
  - [x] Criar post com upload de imagem e legenda
  - [x] Feed mostra posts de quem o usuário segue
  - [x] Curtir/descurtir um post (feed e detalhe)
  - [x] Comentar inline no feed e no detalhe do post
  - [x] Perfil próprio e de terceiros (com `?user=<username>`)
  - [x] Seguir/deixar de seguir outro perfil
  - [x] Editar perfil (nome, username, bio) e upload de avatar
  - [x] Listagem de seguidores/seguindo
