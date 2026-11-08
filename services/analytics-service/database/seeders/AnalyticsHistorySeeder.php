<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AnalyticsHistorySeeder extends Seeder
{
    /**
     * Seeds 90 days of daily historical time-series data for Diagnostic and Predictive analysis,
     * focusing heavily on Approval SLA Turnaround times, High-Risk Escalations, and Risk Score trends.
     */
    public function run(): void
    {
        $today = Carbon::today();
        
        $metrics = [];

        // Generate 90 days of daily data (from 90 days ago up to today)
        for ($day = 90; $day >= 0; $day--) {
            $date = $today->copy()->subDays($day)->toDateString();

            // 1. Approval SLA Turnaround Hours
            // Simulating a trend where approval hours increased over the last 30 days due to Luzon region backlog
            $baseApprovalHours = $day < 30 ? (3.5 + ($day / 15) * 0.4) : (2.2 + (sin($day) * 0.3));
            $approvalHours = round(max(1.0, $baseApprovalHours), 2);

            $metrics[] = [
                'metric_type'    => 'contracts_avg_approval_hours',
                'source_service' => 'contract-management',
                'source_record_id' => null,
                'metric_value'   => $approvalHours,
                'metric_date'    => $date,
                'metadata'       => json_encode([
                    'trend_direction' => $day < 30 ? 'slower' : 'stable',
                ]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // 2. High-Risk Pending & Escalated Approvals
            $pendingCount = $day < 30 ? rand(8, 15) : rand(2, 6);
            $escalatedCount = $day < 30 ? rand(3, 7) : rand(0, 2);

            $metrics[] = [
                'metric_type'    => 'high_risk_approvals_pending',
                'source_service' => 'contract-management',
                'source_record_id' => null,
                'metric_value'   => $pendingCount,
                'metric_date'    => $date,
                'metadata'       => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            $metrics[] = [
                'metric_type'    => 'high_risk_approvals_escalated',
                'source_service' => 'contract-management',
                'source_record_id' => null,
                'metric_value'   => $escalatedCount,
                'metric_date'    => $date,
                'metadata'       => json_encode([
                    'by_status' => [
                        'Pending Review' => rand(4, 10),
                        'Approved'       => rand(15, 25),
                        'Under Legal Review' => rand(2, 6),
                    ],
                    'by_category' => [
                        'Service Level Agreement' => rand(10, 18),
                        'Equipment Purchase'      => rand(8, 14),
                        'NDAs'                    => rand(5, 12),
                    ],
                ]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // 3. Average Contract Risk Score (0-10 scale)
            $baseRiskScore = $day < 30 ? (6.8 + ($day / 20) * 0.3) : (5.2 + (cos($day) * 0.4));
            $riskScore = round(min(10.0, max(1.0, $baseRiskScore)), 2);

            $metrics[] = [
                'metric_type'    => 'avg_risk_score',
                'source_service' => 'ai-service',
                'source_record_id' => null,
                'metric_value'   => $riskScore,
                'metric_date'    => $date,
                'metadata'       => json_encode([
                    'by_risk_level' => [
                        'Low Risk'    => rand(15, 30),
                        'Medium Risk' => rand(10, 20),
                        'High Risk'   => rand(5, 12),
                    ],
                ]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // 4. Contract Counts
            $metrics[] = [
                'metric_type'    => 'contracts_total',
                'source_service' => 'contract-management',
                'source_record_id' => null,
                'metric_value'   => 100,
                'metric_date'    => $date,
                'metadata'       => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        // Upsert (rather than raw insert) so re-running this seeder never
        // creates duplicate rows for the same (metric_type, source_service,
        // metric_date) — that duplication previously corrupted the
        // predictive forecast's historical series.
        foreach (array_chunk($metrics, 200) as $chunk) {
            DB::table('aggregated_metrics')->upsert(
                $chunk,
                ['metric_type', 'source_service', 'metric_date'],
                ['metric_value', 'metadata', 'updated_at']
            );
        }

        $this->command->info('Seeded 90 days of daily analytics metrics for Diagnostic and Predictive analysis.');
    }
}
