<?php

namespace Database\Seeders;

use App\Models\Customer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

class CustomerSeeder extends Seeder
{
    use DummyJsonSeeder;

    private const DEFAULT_PASSWORD_HASH = '$2y$12$nfoSaTn4rEbIfKM6VpEOBeFY2E76fkgay6UugFg8y7VE.X2B2MyG6';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = $this->makeClient();
        $team = $this->getDefaultTeam();

        $customers = $this->fetchCustomers($client);

        $customers->each(function (array $customer) use ($team): void {
            Customer::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'email' => $customer['email'],
                ],
                [
                    'team_id' => $team->id,
                    'name' => $customer['name'],
                    'password' => $customer['password'],
                ],
            );
        });
    }

    /**
     * @return Collection<int, array{name: string, email: string, password: string}>
     *
     * @throws GuzzleException
     * @throws \JsonException
     */
    private function fetchCustomers(Client $client): Collection
    {
        $response = $client->request('GET', 'users?limit=0');

        $payload = json_decode(
            $response->getBody()->getContents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $customers = $payload['users'] ?? null;

        if (! is_array($customers)) {
            throw new RuntimeException(
                'DummyJson users response did not include a valid users array.',
            );
        }

        $customers = collect($customers)
            ->map(
                fn (mixed $customer): ?array => $this->normalizeCustomer(
                    $customer,
                ),
            )
            ->filter()
            ->unique('email')
            ->values();

        if ($customers->isEmpty()) {
            throw new RuntimeException(
                'DummyJson users response did not include valid customer rows.',
            );
        }

        return $customers;
    }

    /**
     * @return array{name: string, email: string, password: string}|null
     */
    private function normalizeCustomer(mixed $customer): ?array
    {
        if (! is_array($customer)) {
            return null;
        }

        $firstName = $customer['firstName'] ?? null;
        $lastName = $customer['lastName'] ?? null;
        $email = $customer['email'] ?? null;

        if (
            ! is_string($firstName) ||
            ! is_string($lastName) ||
            ! is_string($email)
        ) {
            return null;
        }

        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);

        if ($firstName === '' || $lastName === '' || $email === '') {
            return null;
        }

        return [
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => self::DEFAULT_PASSWORD_HASH,
        ];
    }
}
