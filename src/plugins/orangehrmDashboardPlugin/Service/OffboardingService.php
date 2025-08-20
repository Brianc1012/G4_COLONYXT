<?php

namespace OrangeHRM\Dashboard\Service;

use OrangeHRM\Core\Traits\ServiceContainerTrait;
use OrangeHRM\Dashboard\Dao\OffboardingDao;

class OffboardingService
{
    use ServiceContainerTrait;

    private ?OffboardingDao $offboardingDao = null;

    public function getOffboardingDao(): OffboardingDao
    {
        if (!$this->offboardingDao instanceof OffboardingDao) {
            $this->offboardingDao = new OffboardingDao();
        }
        return $this->offboardingDao;
    }

    public function getOffboardingAnalytics(): array
    {
        $dao = $this->getOffboardingDao();
        
        $currentYear = date('Y');
        $startDate = $currentYear . '-01-01';
        $endDate = $currentYear . '-12-31';
        
        $totalOffboarded = $dao->getTerminatedEmployeesCount($startDate, $endDate);
        $totalEmployees = $dao->getTotalActiveEmployees();
        $turnoverRate = $totalEmployees > 0 ? round(($totalOffboarded / $totalEmployees) * 100, 1) : 0;
        
        return [
            'totalOffboarded' => $totalOffboarded,
            'turnoverRate' => $turnoverRate,
            'totalActiveEmployees' => $totalEmployees,
            'reasonBreakdown' => $dao->getTerminationReasonsBreakdown($startDate, $endDate),
            'recentDepartures' => $dao->getRecentDepartures(5),
            'monthlyTrend' => $dao->getMonthlyTerminationTrend(),
            'departmentBreakdown' => $dao->getDepartmentTerminationBreakdown($startDate, $endDate),
            'jobTitleBreakdown' => $dao->getTerminationsByJobTitle($startDate, $endDate),
            'averageTenureMonths' => $dao->getAverageTenure(),
            'bpoMetrics' => $this->calculateBPOMetrics($turnoverRate, $totalOffboarded),
        ];
    }

    private function calculateBPOMetrics(float $turnoverRate, int $totalOffboarded): array
    {
        // BPO industry benchmarks
        $benchmarks = [
            'low' => 25,      // Below 25% is good for BPO
            'moderate' => 35, // 25-35% is acceptable
            'high' => 50,     // 35-50% is concerning
            'critical' => 50  // Above 50% is critical
        ];

        $alertLevel = 'good';
        if ($turnoverRate >= $benchmarks['critical']) {
            $alertLevel = 'critical';
        } elseif ($turnoverRate >= $benchmarks['high']) {
            $alertLevel = 'high';
        } elseif ($turnoverRate >= $benchmarks['moderate']) {
            $alertLevel = 'moderate';
        }

        return [
            'alertLevel' => $alertLevel,
            'benchmarks' => $benchmarks,
            'recommendations' => $this->getBPORecommendations($alertLevel, $turnoverRate),
            'industryAverage' => 35, // BPO industry average
            'riskFactors' => $this->calculateRiskFactors($turnoverRate, $totalOffboarded)
        ];
    }

    private function getBPORecommendations(string $alertLevel, float $turnoverRate): array
    {
        $recommendations = [];

        switch ($alertLevel) {
            case 'critical':
                $recommendations = [
                    'Implement immediate retention bonuses',
                    'Conduct urgent exit interviews',
                    'Review and adjust compensation packages',
                    'Enhance work-life balance programs',
                    'Improve shift scheduling flexibility',
                    'Launch employee engagement initiatives'
                ];
                break;
            case 'high':
                $recommendations = [
                    'Review compensation competitiveness',
                    'Enhance career development programs',
                    'Improve management training',
                    'Implement flexible work arrangements',
                    'Strengthen employee recognition programs'
                ];
                break;
            case 'moderate':
                $recommendations = [
                    'Monitor emerging trends closely',
                    'Enhance onboarding process',
                    'Improve team communication',
                    'Consider performance incentives'
                ];
                break;
            default:
                $recommendations = [
                    'Maintain current retention strategies',
                    'Continue regular engagement surveys',
                    'Monitor industry trends'
                ];
        }

        return $recommendations;
    }

    private function calculateRiskFactors(float $turnoverRate, int $totalOffboarded): array
    {
        return [
            'turnoverTrend' => $turnoverRate > 35 ? 'increasing' : 'stable',
            'seasonalImpact' => date('n') >= 11 || date('n') <= 2 ? 'high' : 'normal', // Holiday season
            'volumeRisk' => $totalOffboarded > 50 ? 'high' : 'normal',
            'costImpact' => round($totalOffboarded * 15000, 0) // Estimated cost per termination
        ];
    }
}
