<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Combines active contract values from contract-management with actual
 * reimbursements and cash advances from SERMS to compute real-time burn rate
 * and True Net Contract Margin.
 */
class ContractMarginService
{
    public function __construct(protected MetricsClient $client)
    {
    }

    /**
     * @return array{serms_connected: bool, summary: array<string, float|int>, contracts: array<int, array<string, mixed>>}
     */
    public function compute(): array
    {
        $contractsPayload = $this->client->activeContracts();
        $contracts = $contractsPayload['data'] ?? [];

        $sermsPayload = $this->client->sermsExpensesByContract();
        $sermsConnected = $sermsPayload !== null;
        $expensesByContract = $this->indexSermsExpenses($sermsPayload['data'] ?? []);

        $totalValue = 0.0;
        $totalExpenses = 0.0;
        $rows = [];

        foreach ($contracts as $contract) {
            $contractId = (int) ($contract['contract_id'] ?? 0);
            $contractValue = isset($contract['contract_value']) && $contract['contract_value'] !== null
                ? (float) $contract['contract_value']
                : null;

            $expenseEntry = $expensesByContract[$contractId] ?? null;
            $actualExpenses = (float) ($expenseEntry['total_expenses'] ?? 0.0);
            $breakdown = $expenseEntry['breakdown'] ?? [
                'travel'        => 0.0,
                'spare_parts'   => 0.0,
                'field_service' => 0.0,
                'other'         => 0.0,
            ];

            $burnRatePct = ($contractValue !== null && $contractValue > 0)
                ? round(($actualExpenses / $contractValue) * 100, 2)
                : null;

            $trueNetMargin = $contractValue !== null
                ? round($contractValue - $actualExpenses, 2)
                : null;

            $marginPct = ($contractValue !== null && $contractValue > 0)
                ? round((($contractValue - $actualExpenses) / $contractValue) * 100, 2)
                : null;

            if ($contractValue !== null) {
                $totalValue += $contractValue;
            }
            $totalExpenses += $actualExpenses;

            $rows[] = [
                'contract_id'          => $contractId,
                'contract_code'        => $contract['contract_code'] ?? null,
                'client_name'          => $contract['client_name'] ?? null,
                'category'             => $contract['category'] ?? null,
                'total_contract_value' => $contractValue,
                'actual_expenses'      => round($actualExpenses, 2),
                'expense_breakdown'    => $breakdown,
                'burn_rate_pct'        => $burnRatePct,
                'true_net_margin'      => $trueNetMargin,
                'margin_pct'           => $marginPct,
                'start_date'           => $contract['start_date'] ?? null,
                'end_date'             => $contract['end_date'] ?? null,
            ];
        }

        return [
            'serms_connected' => $sermsConnected,
            'summary' => [
                'contracts_count'      => count($rows),
                'total_contract_value' => round($totalValue, 2),
                'total_expenses'       => round($totalExpenses, 2),
                'total_net_margin'     => round($totalValue - $totalExpenses, 2),
            ],
            'contracts' => $rows,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function indexSermsExpenses(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            if (isset($item['contract_id'])) {
                $indexed[(int) $item['contract_id']] = $item;
            }
        }

        return $indexed;
    }
}
