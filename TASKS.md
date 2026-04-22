# InstaClone (frontend) - Status alinhado ao codigo

## 1 - Setup do Projeto

- [x] Inicializar projeto com Vue 3 + Vite
- [x] Configurar estrutura de pastas por dominio
- [x] Configurar Vue Router com fallback 404
- [x] Configurar Pinia para estado compartilhado
- [x] Definir tema global customizado em `src/assets/styles/theme.css`
- [x] Configurar axios com interceptor de token e tratamento de `401`
- [x] Versionar `.env.example` e `.dockerignore`

## 2 - Autenticacao

- [x] Tela de login
- [x] Tela de cadastro
- [x] Centralizar sessao autenticada no store `auth`
- [x] Salvar e remover token no `localStorage`
- [x] Reidratar sessao via `GET /auth/me`
- [x] Redirecionar visitante para `/login` e usuario autenticado para `/feed`
- [x] Proteger rotas com guards `requiresAuth` e `requiresGuest`

## 3 - Layout e Navegacao

- [x] Layout responsivo base para area autenticada e area de visitante
- [x] Navegacao inferior no mobile e lateral no desktop
- [x] Exibir `Home`, `Descobrir`, `Criar` e `Perfil` no shell autenticado
- [x] Usar slots no layout base para `sidebar`, `header`, `main` e `footer`
- [x] Usar componente dinamico no `AppLayout` para troca de views

## 4 - Feed

- [x] Componente de post com autor, imagem, legenda, data e contadores
- [x] Listagem via `GET /feed` com cursor pagination
- [x] Botao "carregar mais" usando `next_cursor`
- [x] Store Pinia como fonte reativa do feed
- [x] Curtir e descurtir integrados a API
- [x] Comentario inline integrado a API
- [x] Link para o perfil do autor

## 5 - Descobrir

- [x] Tela de sugestoes de perfis via `GET /users/suggestions`
- [x] Montar relacionamento do viewer com `GET /users/{viewerId}/following`
- [x] Seguir e deixar de seguir direto da lista
- [x] Abrir perfil proprio ou de terceiros a partir dos cards
- [x] Carregar mais resultados por pagina

## 6 - Criar Post

- [x] Upload real de imagem com `multipart/form-data`
- [x] Preview com `URL.createObjectURL`
- [x] Validar formatos JPG, PNG e WEBP
- [x] Validar limite de 5 MB
- [x] Campo de legenda com contador
- [x] Publicar com `POST /posts`
- [x] Mostrar feedback de sucesso ou erro

## 7 - Perfil

- [x] Tela de perfil com foto, bio e contadores
- [x] Abrir perfil proprio ou de terceiros com `?user=<username>`
- [x] Grid de posts do usuario com `GET /users/{id}/posts`
- [x] Listar seguidores e seguindo com `GET /users/{id}/followers` e `GET /users/{id}/following`
- [x] Consultar `GET /users/{id}/is-following` para perfis de terceiros
- [x] Seguir e deixar de seguir perfil
- [x] Exibir botao de editar perfil apenas no perfil proprio
- [x] Editar nome, username e bio em `/perfil/editar`
- [x] Upload de avatar em `POST /users/me/avatar`

## 8 - Listas de Conexao

- [x] Tela dedicada para seguidores
- [x] Tela dedicada para seguindo
- [x] Paginacao por pagina nas listas
- [x] Acao de seguir e deixar de seguir direto da lista

## 9 - Detalhes do Post

- [x] Tela individual do post via `GET /posts/{id}`
- [x] Listagem paginada de comentarios com botao "carregar mais"
- [x] Campo para adicionar comentario
- [x] Contagem de curtidas e comentarios vindo do backend
- [x] Deletar comentario quando o viewer e o autor
- [x] Deletar post quando o viewer e o autor

## 10 - Docker e Entrega

- [x] `Dockerfile` multi-stage
- [x] `docker/nginx.conf` com fallback para `index.html`
- [x] `compose.yaml` expondo `3000:80`
- [x] `.dockerignore` excluindo artefatos locais
- [x] Build de producao do frontend (`npm run build`)

## 11 - Superficie de API pronta sem tela dedicada

- [x] `auth.refresh()`
- [x] `users.search()`
- [x] `posts.update()`
- [x] `comments.update()`
- [x] `likes.likers()`
- [x] `notifications.service.js`
