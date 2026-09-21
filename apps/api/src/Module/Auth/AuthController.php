<?php

declare(strict_types=1);

namespace StagasBites\Module\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StagasBites\Helper\JsonResponse;
use StagasBites\Repository\UserRepository;
use StagasBites\Service\AuthService;
use StagasBites\Service\ValidationService;
use Symfony\Component\Validator\Constraints as Assert;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRepository $users,
        private readonly ValidationService $validator,
    ) {
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'first_name' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            'last_name' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            'phone' => [new Assert\Length(max: 30)],
            'password' => ValidationService::password(),
        ]);

        return JsonResponse::success($response, $this->auth->register($data), 201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'password' => [new Assert\NotBlank()],
        ]);

        return JsonResponse::success($response, $this->auth->login($data['email'], $data['password']));
    }

    public function refresh(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'refresh_token' => [new Assert\NotBlank(), new Assert\Type('string')],
        ]);

        return JsonResponse::success($response, $this->auth->refresh($data['refresh_token']));
    }

    public function logout(Request $request, Response $response): Response
    {
        $token = ((array) $request->getParsedBody())['refresh_token'] ?? null;
        if (is_string($token) && $token !== '') {
            $this->auth->logout($token);
        }

        return JsonResponse::success($response, null, 200, 'Signed out.');
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'email' => [new Assert\NotBlank(), new Assert\Email()],
        ]);
        $this->auth->requestPasswordReset($data['email']);

        return JsonResponse::success($response, null, 200, 'If that email has an account, a reset link is on its way.');
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'token' => [new Assert\NotBlank(), new Assert\Type('string')],
            'password' => ValidationService::password(),
        ]);
        $this->auth->resetPassword($data['token'], $data['password']);

        return JsonResponse::success($response, null, 200, 'Password updated. You can now sign in.');
    }

    public function me(Request $request, Response $response): Response
    {
        return JsonResponse::success($response, $this->users->findOrFail((string) $request->getAttribute('user_id'))->toArray());
    }

    public function updateMe(Request $request, Response $response): Response
    {
        $data = $this->validator->validate((array) $request->getParsedBody(), [
            'first_name' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            'last_name' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            'phone' => [new Assert\Length(max: 30)],
        ]);

        $user = $this->users->findOrFail((string) $request->getAttribute('user_id'));
        $user->setFirstName($data['first_name']);
        $user->setLastName($data['last_name']);
        $user->setPhone($data['phone']);
        $this->users->save($user);

        return JsonResponse::success($response, $user->toArray());
    }
}
