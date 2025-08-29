<?php

namespace OrangeHRM\Dashboard\Api;

use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Dashboard\Service\OffboardingService;
use OrangeHRM\Entity\Employee;

class OffboardingAnalyticsAPI extends Endpoint implements CollectionEndpoint
{
    use UserRoleManagerTrait;
    use AuthUserTrait;
    
    private ?OffboardingService $offboardingService = null;

    public function getOffboardingService(): OffboardingService
    {
        if (!$this->offboardingService instanceof OffboardingService) {
            $this->offboardingService = new OffboardingService();
        }
        return $this->offboardingService;
    }

    public function getAll(): EndpointCollectionResult
    {
        try {
            // Check if user has permission to view dashboard analytics
            $authUser = $this->getAuthUser();
            if (!$authUser || !$authUser->getEmpNumber()) {
                // Instead of throwing exception, return empty data for now
                error_log("OffboardingAnalyticsAPI: No authenticated user");
                return $this->getEmptyResponse();
            }

            // Check if user has access to HR data (similar to other dashboard APIs)
            $accessibleEmpNumbers = $this->getUserRoleManager()->getAccessibleEntityIds(Employee::class);
            if (empty($accessibleEmpNumbers)) {
                // If user has no accessible employees, return empty data instead of error
                error_log("OffboardingAnalyticsAPI: No accessible employees for user");
                return $this->getEmptyResponse();
            }

            // Try to get analytics data
            $analytics = $this->getOffboardingService()->getOffboardingAnalytics();
            
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("OffboardingAnalyticsAPI Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Return empty response instead of throwing exception
            return $this->getEmptyResponse();
        }

        return new EndpointCollectionResult(
            ArrayModel::class,
            [$analytics],
            new ParameterBag([])
        );
    }

    private function getEmptyResponse(): EndpointCollectionResult
    {
        $analytics = [
            'totalOffboarded' => 0,
            'turnoverRate' => 0.0,
            'totalActiveEmployees' => 0,
            'reasonBreakdown' => [],
            'recentDepartures' => [],
            'monthlyTrend' => [],
            'departmentBreakdown' => [],
            'jobTitleBreakdown' => [],
            'averageTenureMonths' => 0.0,
            'bpoMetrics' => [
                'alertLevel' => 'good',
                'recommendations' => ['No data available - please check authentication'],
                'industryAverage' => 35,
                'benchmarks' => [
                    'low' => 25,
                    'moderate' => 35,
                    'high' => 50,
                    'critical' => 50
                ],
                'riskFactors' => []
            ]
        ];

        return new EndpointCollectionResult(
            ArrayModel::class,
            [$analytics],
            new ParameterBag([])
        );
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    public function create(): EndpointCollectionResult
    {
        throw $this->getNotImplementedException();
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    public function delete(): EndpointResourceResult
    {
        throw $this->getNotImplementedException();
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    public function update(): EndpointResourceResult
    {
        throw $this->getNotImplementedException();
    }

    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}
