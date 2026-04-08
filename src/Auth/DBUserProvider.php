<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Atlas\DB;

/**
 * DB User Provider
 *
 * Retrieves users using the Atlas Query Builder.
 */
class DBUserProvider implements UserProvider
{
    public function __construct(
        protected string $table  = 'users',
        protected string $model  = \stdClass::class
    ) {}

    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $user = DB::table($this->table)->where('id', $identifier)->first();
        return $this->mapToUser($user);
    }

    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable
    {
        $user = DB::table($this->table)
            ->where('id', $identifier)
            ->where('remember_token', $token)
            ->first();

        return $this->mapToUser($user);
    }

    public function updateRememberToken(Authenticatable $user, string $token): void
    {
        DB::table($this->table)
            ->where('id', $user->getAuthIdentifier())
            ->updateRecord(['remember_token' => $token]);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        $query = DB::table($this->table);

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }
            $query->where($key, $value);
        }

        $user = $query->first();

        return $this->mapToUser($user);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! isset($credentials['password'])) {
            return false;
        }

        return password_verify($credentials['password'], $user->getAuthPassword());
    }

    protected function mapToUser(?object $data): ?Authenticatable
    {
        if (! $data) {
            return null;
        }

        // Return a proxy or the data cast to an anonymous class that implements Authenticatable
        return new class($data) implements Authenticatable {
            public function __construct(protected object $data) {}
            public function getAuthIdentifier(): mixed { return $this->data->id; }
            public function getAuthPassword(): string { return $this->data->password; }
            public function getRememberTokenName(): string { return 'remember_token'; }
            public function getRememberToken(): ?string { return $this->data->remember_token ?? null; }
            public function setRememberToken(string $token): void { $this->data->remember_token = $token; }
            public function __get(string $name) { return $this->data->$name ?? null; }
        };
    }
}
