<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'source_key' => 'local-app',
                'received_at' => null,
                'key' => str_repeat('a', 64),
                'name' => 'RuntimeException',
                'message' => 'Checkout payment provider timeout.',
                'file' => '/var/www/app/Services/CheckoutService.php',
                'line' => 184,
                'raw_exception' => "RuntimeException: Checkout payment provider timeout.\n#0 /var/www/app/Services/CheckoutService.php(184): CheckoutService->charge()\n#1 /var/www/app/Http/Controllers/CheckoutController.php(42): CheckoutService->checkout()",
                'request_method' => 'POST',
                'request_endpoint' => '/api/checkout?order=DEV-1001',
                'request_headers' => json_encode([
                    'authorization' => '[MASKED]',
                    'x-request-id' => 'dev-local-001',
                ], JSON_THROW_ON_ERROR),
                'request_payload' => json_encode([
                    'order_id' => 'DEV-1001',
                    'card_token' => '[MASKED]',
                ], JSON_THROW_ON_ERROR),
                'count' => 7,
                'latest_at' => $now,
                'created_at' => $now->copy()->subMinutes(30),
                'updated_at' => $now,
            ],
            [
                'source_key' => 'local-app',
                'received_at' => null,
                'key' => str_repeat('b', 64),
                'name' => 'LogicException',
                'message' => 'Inventory sync job could not reserve requested stock.',
                'file' => '/var/www/app/Jobs/SyncInventory.php',
                'line' => 57,
                'raw_exception' => "LogicException: Inventory sync job could not reserve requested stock.\n#0 /var/www/app/Jobs/SyncInventory.php(57): SyncInventory->reserveStock()",
                'request_method' => null,
                'request_endpoint' => 'queue:redis:inventory',
                'request_headers' => null,
                'request_payload' => json_encode([
                    'job' => 'App\\Jobs\\SyncInventory',
                    'sku' => 'SKU-RED-XL',
                    'attempts' => 3,
                ], JSON_THROW_ON_ERROR),
                'count' => 4,
                'latest_at' => $now->copy()->subMinutes(14),
                'created_at' => $now->copy()->subMinutes(24),
                'updated_at' => $now->copy()->subMinutes(14),
            ],
            [
                'source_key' => 'service-a',
                'received_at' => $now->copy()->subMinutes(11),
                'key' => str_repeat('c', 64),
                'name' => 'LogicException',
                'message' => 'Remote service failed to map order state.',
                'file' => '/srv/service-a/app/OrderStateMapper.php',
                'line' => 31,
                'raw_exception' => "LogicException: Remote service failed to map order state.\n#0 /srv/service-a/app/OrderStateMapper.php(31): OrderStateMapper->map()",
                'request_method' => 'PUT',
                'request_endpoint' => '/internal/orders/DEV-1001/state',
                'request_headers' => json_encode([
                    'authorization' => '[MASKED]',
                    'x-service' => 'service-a',
                ], JSON_THROW_ON_ERROR),
                'request_payload' => json_encode([
                    'state' => 'unknown',
                ], JSON_THROW_ON_ERROR),
                'count' => 4,
                'latest_at' => $now->copy()->subMinutes(12),
                'created_at' => $now->copy()->subMinutes(18),
                'updated_at' => $now->copy()->subMinutes(12),
            ],
            [
                'source_key' => 'service-b',
                'received_at' => $now->copy()->subMinutes(9),
                'key' => str_repeat('d', 64),
                'name' => 'RuntimeException',
                'message' => 'Billing reconciliation import exceeded memory limit.',
                'file' => '/srv/service-b/app/Console/ImportBillingReconciliation.php',
                'line' => 122,
                'raw_exception' => "RuntimeException: Billing reconciliation import exceeded memory limit.\n#0 /srv/service-b/app/Console/ImportBillingReconciliation.php(122): ImportBillingReconciliation->readCsv()",
                'request_method' => null,
                'request_endpoint' => 'console:billing:reconcile',
                'request_headers' => null,
                'request_payload' => json_encode([
                    'command' => 'billing:reconcile',
                    'file' => 's3://dev-billing/reconcile-2026-05-27.csv',
                ], JSON_THROW_ON_ERROR),
                'count' => 9,
                'latest_at' => $now->copy()->subMinutes(9),
                'created_at' => $now->copy()->subMinutes(34),
                'updated_at' => $now->copy()->subMinutes(9),
            ],
            [
                'source_key' => 'service-b',
                'received_at' => $now->copy()->subMinutes(21),
                'key' => str_repeat('e', 64),
                'name' => 'UnexpectedValueException',
                'message' => 'Customer profile response omitted the required tier field.',
                'file' => '/srv/service-b/app/Clients/CustomerProfileClient.php',
                'line' => 88,
                'raw_exception' => "UnexpectedValueException: Customer profile response omitted the required tier field.\n#0 /srv/service-b/app/Clients/CustomerProfileClient.php(88): CustomerProfileClient->normalizeProfile()",
                'request_method' => 'GET',
                'request_endpoint' => '/internal/customers/CUS-4821/profile',
                'request_headers' => json_encode([
                    'authorization' => '[MASKED]',
                    'x-service' => 'service-b',
                ], JSON_THROW_ON_ERROR),
                'request_payload' => json_encode([
                    'customer_id' => 'CUS-4821',
                ], JSON_THROW_ON_ERROR),
                'count' => 3,
                'latest_at' => $now->copy()->subMinutes(21),
                'created_at' => $now->copy()->subMinutes(41),
                'updated_at' => $now->copy()->subMinutes(21),
            ],
            [
                'source_key' => 'service-b',
                'received_at' => $now->copy()->subMinutes(39),
                'key' => str_repeat('f', 64),
                'name' => 'InvalidArgumentException',
                'message' => 'Refund webhook contained an unsupported currency code.',
                'file' => '/srv/service-b/app/Http/Controllers/RefundWebhookController.php',
                'line' => 45,
                'raw_exception' => "InvalidArgumentException: Refund webhook contained an unsupported currency code.\n#0 /srv/service-b/app/Http/Controllers/RefundWebhookController.php(45): RefundWebhookController->validateCurrency()",
                'request_method' => 'POST',
                'request_endpoint' => '/webhooks/refunds',
                'request_headers' => json_encode([
                    'x-request-id' => 'dev-service-b-003',
                ], JSON_THROW_ON_ERROR),
                'request_payload' => json_encode([
                    'refund_id' => 'RF-9001',
                    'currency' => 'ZZZ',
                ], JSON_THROW_ON_ERROR),
                'count' => 1,
                'latest_at' => $now->copy()->subMinutes(39),
                'created_at' => $now->copy()->subMinutes(39),
                'updated_at' => $now->copy()->subMinutes(39),
            ],
        ];

        DB::table('exception_logs')
            ->whereIn('key', array_column($rows, 'key'))
            ->delete();

        DB::table('exception_logs')->insert($rows);
    }
}
