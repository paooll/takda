<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Queue;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'owner@takda.test'],
            [
                'name' => 'Liza Santos',
                'password' => Hash::make('password'),
                'role' => User::ROLE_BUSINESS,
                'phone' => '+639171000001',
            ],
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@takda.test'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CUSTOMER,
                'phone' => '+639171000002',
            ],
        );

        $business = Business::updateOrCreate(
            ['slug' => 'pasig-city-hall'],
            [
                'owner_id' => $owner->id,
                'name' => 'Pasig City Hall — Payments',
                'description' => 'Real property tax, business permits, and cashier services.',
                'address' => '14 Shaw Blvd, Pasig City',
                'avg_service_minutes' => 12,
                'is_open' => true,
            ],
        );

        $queues = collect([
            ['name' => 'Cashier', 'code_prefix' => 'C', 'avg_service_minutes' => 12],
            ['name' => 'Business Permits', 'code_prefix' => 'P', 'avg_service_minutes' => 20],
            ['name' => 'Information Desk', 'code_prefix' => 'I', 'avg_service_minutes' => 5],
        ])->map(fn (array $data) => Queue::updateOrCreate(
            ['business_id' => $business->id, 'name' => $data['name']],
            $data + ['business_id' => $business->id, 'is_active' => true],
        ));

        // Give the cashier line some realistic history plus a live tail.
        $cashier = $queues->firstWhere('code_prefix', 'C');

        if ($cashier->tickets()->count() === 0) {
            // Issue real codes through the queue allocator, and give the waiting
            // customers distinct accounts so the demo board looks like a real line.
            $waiting = collect(['Ana Reyes', 'Carlo Mendoza', $customer->name])->map(
                fn (string $name) => User::firstOrCreate(
                    ['email' => str($name)->lower()->replace(' ', '.')->append('@example.test')->toString()],
                    ['name' => $name, 'password' => Hash::make('password')],
                ),
            );

            foreach (range(1, 6) as $ignored) {
                $number = $cashier->issueTicketNumber();
                Ticket::factory()->done(11)->create([
                    'queue_id' => $cashier->id,
                    'sequence' => $number,
                    'code' => $cashier->formatTicketCode($number),
                ]);
            }

            foreach ($waiting as $person) {
                $number = $cashier->issueTicketNumber();
                Ticket::factory()->create([
                    'queue_id' => $cashier->id,
                    'sequence' => $number,
                    'code' => $cashier->formatTicketCode($number),
                    'user_id' => $person->id,
                ]);
            }
        }

        Appointment::updateOrCreate(
            ['reference' => 'DEMO1234'],
            [
                'business_id' => $business->id,
                'user_id' => $customer->id,
                'starts_at' => now()->addDay()->setTime(9, 0),
                'ends_at' => now()->addDay()->setTime(9, 30),
                'status' => Appointment::STATUS_SCHEDULED,
                'purpose' => 'Business permit renewal',
            ],
        );

        $this->command?->info('Seeded demo data.');
        $this->command?->info('  Business owner: owner@takda.test / password');
        $this->command?->info('  Customer:       customer@takda.test / password');
    }
}
