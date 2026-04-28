<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'InstaClone API',
    version: '1.0.0',
    description: 'API REST para rede social inspirada no Instagram.'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class SwaggerController extends Controller
{
    // Auth

    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Auth'],
        summary: 'Cadastrar usuário',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'username', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Matheus'),
                new OA\Property(property: 'username', type: 'string', example: 'matheus'),
                new OA\Property(property: 'email', type: 'string', example: 'matheus@email.com'),
                new OA\Property(property: 'password', type: 'string', example: 'senha1234'),
                new OA\Property(property: 'password_confirmation', type: 'string', example: 'senha1234'),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Usuário criado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Login',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'matheus@email.com'),
                new OA\Property(property: 'password', type: 'string', example: 'senha1234'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Login efetuado'),
            new OA\Response(response: 422, description: 'Credenciais inválidas'),
        ]
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Auth'],
        summary: 'Logout',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Desconectado')]
    )]
    public function logout(): void {}

    #[OA\Post(
        path: '/api/auth/refresh',
        tags: ['Auth'],
        summary: 'Renovar token',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Novo token gerado')]
    )]
    public function refresh(): void {}

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Auth'],
        summary: 'Usuário autenticado',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Dados do usuário')]
    )]
    public function me(): void {}

    // Users

    #[OA\Get(
        path: '/api/users/{username}',
        tags: ['Users'],
        summary: 'Perfil por username',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Perfil do usuário'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function showUser(): void {}

    #[OA\Put(
        path: '/api/users/me',
        tags: ['Users'],
        summary: 'Editar perfil',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'username', type: 'string'),
            new OA\Property(property: 'bio', type: 'string'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Perfil atualizado')]
    )]
    public function updateMe(): void {}

    #[OA\Post(
        path: '/api/users/me/avatar',
        tags: ['Users'],
        summary: 'Atualizar avatar',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(properties: [new OA\Property(property: 'avatar', type: 'string', format: 'binary')])
        )),
        responses: [new OA\Response(response: 200, description: 'Avatar atualizado')]
    )]
    public function uploadAvatar(): void {}

    #[OA\Get(
        path: '/api/users/search',
        tags: ['Users'],
        summary: 'Buscar usuários',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Lista de usuários')]
    )]
    public function searchUsers(): void {}

    #[OA\Get(
        path: '/api/users/suggestions',
        tags: ['Users'],
        summary: 'Sugestões de quem seguir',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Lista de sugestões')]
    )]
    public function suggestions(): void {}

    #[OA\Get(
        path: '/api/users/{user}/posts',
        tags: ['Users'],
        summary: 'Posts do usuário',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lista de posts')]
    )]
    public function userPosts(): void {}

    #[OA\Post(
        path: '/api/users/{user}/follow',
        tags: ['Users'],
        summary: 'Seguir usuário',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Seguindo')]
    )]
    public function follow(): void {}

    #[OA\Delete(
        path: '/api/users/{user}/follow',
        tags: ['Users'],
        summary: 'Deixar de seguir',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deixou de seguir')]
    )]
    public function unfollow(): void {}

    #[OA\Get(
        path: '/api/users/{user}/followers',
        tags: ['Users'],
        summary: 'Seguidores',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lista de seguidores')]
    )]
    public function followers(): void {}

    #[OA\Get(
        path: '/api/users/{user}/following',
        tags: ['Users'],
        summary: 'Quem o usuário segue',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lista de seguidos')]
    )]
    public function following(): void {}

    #[OA\Get(
        path: '/api/users/{user}/is-following',
        tags: ['Users'],
        summary: 'Verificar se segue',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Status de follow')]
    )]
    public function isFollowing(): void {}

    // Feed

    #[OA\Get(
        path: '/api/feed',
        tags: ['Feed'],
        summary: 'Feed paginado por cursor',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'cursor', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Posts do feed')]
    )]
    public function feed(): void {}

    // Posts

    #[OA\Post(
        path: '/api/posts',
        tags: ['Posts'],
        summary: 'Criar post',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['image'],
                properties: [
                    new OA\Property(property: 'image', type: 'string', format: 'binary'),
                    new OA\Property(property: 'caption', type: 'string'),
                ]
            )
        )),
        responses: [new OA\Response(response: 201, description: 'Post criado')]
    )]
    public function createPost(): void {}

    #[OA\Get(
        path: '/api/posts/{post}',
        tags: ['Posts'],
        summary: 'Ver post',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dados do post'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function showPost(): void {}

    #[OA\Put(
        path: '/api/posts/{post}',
        tags: ['Posts'],
        summary: 'Editar legenda',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'caption', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Post atualizado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
        ]
    )]
    public function updatePost(): void {}

    #[OA\Delete(
        path: '/api/posts/{post}',
        tags: ['Posts'],
        summary: 'Deletar post',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Post deletado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
        ]
    )]
    public function deletePost(): void {}

    // Likes

    #[OA\Post(
        path: '/api/posts/{post}/like',
        tags: ['Likes'],
        summary: 'Curtir post',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Post curtido')]
    )]
    public function likePost(): void {}

    #[OA\Delete(
        path: '/api/posts/{post}/like',
        tags: ['Likes'],
        summary: 'Descurtir post',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Curtida removida')]
    )]
    public function unlikePost(): void {}

    #[OA\Get(
        path: '/api/posts/{post}/likers',
        tags: ['Likes'],
        summary: 'Quem curtiu',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lista de usuários que curtiram')]
    )]
    public function likers(): void {}

    // Comments

    #[OA\Get(
        path: '/api/posts/{post}/comments',
        tags: ['Comments'],
        summary: 'Listar comentários',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Lista de comentários')]
    )]
    public function listComments(): void {}

    #[OA\Post(
        path: '/api/posts/{post}/comments',
        tags: ['Comments'],
        summary: 'Comentar',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['body'],
            properties: [new OA\Property(property: 'body', type: 'string', example: 'Ótimo post!')]
        )),
        responses: [new OA\Response(response: 201, description: 'Comentário criado')]
    )]
    public function createComment(): void {}

    #[OA\Put(
        path: '/api/comments/{comment}',
        tags: ['Comments'],
        summary: 'Editar comentário',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['body'],
            properties: [new OA\Property(property: 'body', type: 'string')]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Comentário atualizado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
        ]
    )]
    public function updateComment(): void {}

    #[OA\Delete(
        path: '/api/comments/{comment}',
        tags: ['Comments'],
        summary: 'Deletar comentário',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Comentário deletado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
        ]
    )]
    public function deleteComment(): void {}
}
