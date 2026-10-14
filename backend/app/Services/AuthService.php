<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly ActivityLogService $activityLog
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $this->roleId(RoleEnum::Participant),
        ]);

        $this->activityLog->record('user.registered', 'auth', "User {$user->name} registered an account.", $user->id);

        return $this->issueToken($user, 'registration');
    }

    /**
     * @return array{user: User, token: string}|null
     */
    public function login(array $data, bool $remember = false): ?array
    {
        $credentials = [
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        if (! Auth::validate($credentials)) {
            return null;
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated.');
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $this->activityLog->record('user.login', 'auth', "User {$user->name} logged in.", $user->id);

        return $this->issueToken($user, $remember ? 'remember' : 'login');
    }

    public function logout(User $user, ?string $tokenId = null): void
    {
        if ($tokenId !== null) {
            $user->tokens()->where('id', $tokenId)->delete();
        } else {
            $token = $user->currentAccessToken();

            if ($token !== null) {
                $token->delete();
            } else {
                // Fallback: resolve the token id directly from the Authorization header.
                $bearer = request()->bearerToken();
                if ($bearer !== null && str_contains($bearer, '|')) {
                    $user->tokens()->where('id', (int) explode('|', $bearer)[0])->delete();
                }
            }
        }

        $this->activityLog->record('user.logout', 'auth', "User {$user->name} logged out.", $user->id);
    }

    /**
     * @return array{status: string, reset_url?: string, token?: string}
     */
    public function sendPasswordResetLink(string $email): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return ['status' => Password::INVALID_USER];
        }

        $token = app('auth.password.broker')->createToken($user);

        $resetUrl = config('app.frontend_url')
            .'/reset-password?token='.urlencode($token)
            .'&email='.urlencode($user->email);

        $user->notify(new \App\Notifications\ResetPasswordNotification($resetUrl));

        $this->activityLog->record('password.forgot', 'auth', "Password reset requested for {$email}.", $user->id);

        return [
            'status' => Password::RESET_LINK_SENT,
            'reset_url' => app()->environment(['local', 'testing']) ? $resetUrl : null,
        ];
    }

    /**
     * @return array{status: string, message: string, token?: string, user?: User}|null
     */
    public function resetPassword(array $data): ?array
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return null;
        }

        $user = User::where('email', $data['email'])->first();

        $this->activityLog->record('password.reset', 'auth', "Password reset for {$data['email']}.", $user?->id);

        return [
            'status' => $status,
            'message' => 'Password has been reset successfully.',
            'token' => $user ? $this->issueToken($user, 'reset')['token'] : null,
            'user' => $user,
        ];
    }

    public function profileUpdated(User $user): void
    {
        $this->activityLog->record('user.profile_updated', 'auth', "User {$user->name} updated their profile.", $user->id);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            abort(422, 'The current password is incorrect.');
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        // Revoke all other sessions except the current one.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $this->activityLog->record('password.changed', 'auth', "User {$user->name} changed their password.", $user->id);
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user, string $purpose): array
    {
        $token = $user->createToken(
            name: $purpose.'::'.now()->format('Y-m-d H:i'),
            abilities: [$user->role->key->value]
        );

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    private function roleId(RoleEnum $role): int
    {
        return \App\Models\Role::where('key', $role->value)->value('id');
    }
}
