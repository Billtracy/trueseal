<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use App\Services\SquadPaymentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'hr@trueseal.test'],
            [
                'name' => 'Amina Okafor',
                'password' => Hash::make('password'),
            ]
        );

        // Bank codes follow the NIP standard used by Squad Transfer API.
        // Using GTBank (000013) for sandbox-compatible transfer testing.
        $institutions = [
            [
                'name' => 'University of Lagos',
                'code' => 'UNILAG',
                'bank_name' => 'GTBank',
                'bank_code' => '000013',
                'account_number' => '0123456789',
                'account_name' => 'UNIVERSITY OF LAGOS',
                'account_last4' => '6789',
            ],
            [
                'name' => 'University of Ibadan',
                'code' => 'UI',
                'bank_name' => 'GTBank',
                'bank_code' => '000013',
                'account_number' => '0123456790',
                'account_name' => 'UNIVERSITY OF IBADAN',
                'account_last4' => '6790',
            ],
            [
                'name' => 'Covenant University',
                'code' => 'CU',
                'bank_name' => 'Access Bank',
                'bank_code' => '000014',
                'account_number' => '0123456791',
                'account_name' => 'COVENANT UNIVERSITY',
                'account_last4' => '6791',
            ],
            [
                'name' => 'Ahmadu Bello University',
                'code' => 'ABU',
                'bank_name' => 'UBA',
                'bank_code' => '000004',
                'account_number' => '0123456792',
                'account_name' => 'AHMADU BELLO UNIVERSITY',
                'account_last4' => '6792',
            ],
        ];

        foreach ($institutions as $data) {
            Institution::updateOrCreate(
                ['code' => $data['code']],
                $data + ['country' => 'Nigeria']
            );
        }

        // Register institutions as sub-merchants with Squad
        $this->registerSubMerchants();
    }

    private function registerSubMerchants(): void
    {
        $squad = app(SquadPaymentService::class);

        if (! $squad->hasCredentials()) {
            $this->command?->warn('Squad credentials not configured — skipping sub-merchant registration.');

            return;
        }

        Institution::whereNull('squad_subaccount_id')
            ->orWhere('squad_subaccount_id', 'like', 'SQD_SUB_%_DEMO')
            ->each(function (Institution $institution) use ($squad) {
                $accountId = $squad->createSubMerchant($institution);

                if ($accountId) {
                    $this->command?->info("Registered {$institution->name} as Squad sub-merchant: {$accountId}");
                } else {
                    $this->command?->warn("Failed to register {$institution->name} with Squad.");
                }
            });
    }
}
