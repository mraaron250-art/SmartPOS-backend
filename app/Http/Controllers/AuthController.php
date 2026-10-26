<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Middleware\Authenticate;
use App\Models\User;
use App\Services\AuditService;
use App\Services\JwtService;

final class AuthController extends Controller
{
    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            throw HttpException::badRequest('Email and password are required.');
        }

        $user = User::query()->where('email', strtolower($email))->first();
        if ($user === null) {
            throw HttpException::unauthorized('Invalid email address or password.');
        }
        if (!$user->isActive()) {
            throw HttpException::forbidden('Your user account is inactive. Please contact management.');
        }
        if (!$user->verifyPassword($password)) {
            AuditService::log($user, [
                'action' => 'AUTH_LOGIN_FAILED',
                'module' => 'AUTH',
                'result' => 'FAILURE',
                'details' => "Failed login attempt for email {$email}",
            ]);
            throw HttpException::unauthorized('Invalid email address or password.');
        }

        $token = (new JwtService())->issue($user);

        AuditService::log($user, [
            'action' => 'AUTH_LOGIN_SUCCESS',
            'module' => 'AUTH',
            'details' => "User {$user->name} logged in successfully",
        ]);

        $session = $user->toSession();

        return Response::json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $session,
            'data' => ['token' => $token, 'user' => $session],
        ])->withCookie(JwtService::COOKIE, $token, JwtService::cookieOptions());
    }

    public function logout(Request $request): Response
    {
        $user = Authenticate::resolveUser($request);
        if ($user !== null) {
            AuditService::log($user, [
                'action' => 'AUTH_LOGOUT',
                'module' => 'AUTH',
                'details' => "User {$user->name} logged out",
            ]);
        }

        return Response::json(['success' => true, 'message' => 'Logged out successfully.'])
            ->withCookie(JwtService::COOKIE, '', JwtService::cookieOptions(0));
    }

    public function me(Request $request): Response
    {
        return Response::json(['success' => true, 'user' => $this->user($request)->toSession()]);
    }
}
