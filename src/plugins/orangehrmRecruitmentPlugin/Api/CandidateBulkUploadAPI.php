<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Recruitment\Api;

use DateTime;
use Exception;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Exception\BadRequestException;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Entity\Candidate;
use OrangeHRM\Entity\CandidateHistory;
use OrangeHRM\Entity\CandidateVacancy;
use OrangeHRM\Entity\Vacancy;
use OrangeHRM\Entity\WorkflowStateMachine;
use OrangeHRM\Recruitment\Service\CandidateService;
use OrangeHRM\Recruitment\Traits\Service\CandidateServiceTrait;
use OrangeHRM\Recruitment\Traits\Service\VacancyServiceTrait;

class CandidateBulkUploadAPI extends Endpoint
{
    use AuthUserTrait;
    use EntityManagerHelperTrait;
    use DateTimeHelperTrait;
    use CandidateServiceTrait;
    use VacancyServiceTrait;

    public const PARAMETER_FILE = 'file';
    public const PARAMETER_CANDIDATES = 'candidates';
    public const PARAMETER_VACANCY_ID = 'vacancyId';

    /**
     * @inheritDoc
     */
    public function getOne(): EndpointResult
    {
        // Return CSV template
        $headers = [
            'First Name',
            'Middle Name', 
            'Last Name',
            'Email',
            'Contact Number',
            'Keywords',
            'Comment',
            'Date of Application'
        ];
        
        return new EndpointResourceResult(ArrayModel::class, [
            'headers' => $headers,
            'sampleData' => [
                [
                    'John',
                    'D',
                    'Doe',
                    'john.doe@example.com',
                    '+1234567890',
                    'PHP, MySQL, Vue.js',
                    'Experienced developer',
                    date('Y-m-d')
                ]
            ]
        ]);
    }

    /**
     * @inheritDoc
     */
    public function create(): EndpointResult
    {
        $candidates = $this->getRequestParams()->getArray(
            RequestParams::PARAM_TYPE_BODY,
            self::PARAMETER_CANDIDATES
        );
        
        $vacancyId = $this->getRequestParams()->getIntOrNull(
            RequestParams::PARAM_TYPE_BODY,
            self::PARAMETER_VACANCY_ID
        );

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        $this->beginTransaction();
        
        try {
            foreach ($candidates as $index => $candidateData) {
                try {
                    $candidate = $this->createCandidateFromData($candidateData, $vacancyId);
                    
                    $results[] = [
                        'row' => $index + 1,
                        'status' => 'success',
                        'candidateId' => $candidate->getId(),
                        'message' => 'Candidate created successfully'
                    ];
                    $successCount++;
                    
                } catch (Exception $e) {
                    $results[] = [
                        'row' => $index + 1,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'data' => $candidateData
                    ];
                    $errorCount++;
                }
            }
            
            $this->commitTransaction();
            
        } catch (Exception $e) {
            $this->rollBackTransaction();
            throw new BadRequestException('Bulk upload failed: ' . $e->getMessage());
        }

        return new EndpointResourceResult(ArrayModel::class, [
            'summary' => [
                'total' => count($candidates),
                'success' => $successCount,
                'errors' => $errorCount
            ],
            'results' => $results
        ]);
    }

    /**
     * Create candidate from data array
     */
    private function createCandidateFromData(array $data, ?int $vacancyId): Candidate
    {
        $candidate = new Candidate();
        $candidate->setFirstName($data['firstName'] ?? '');
        $candidate->setMiddleName($data['middleName'] ?? '');
        $candidate->setLastName($data['lastName'] ?? '');
        $candidate->setEmail($data['email'] ?? '');
        $candidate->setContactNumber($data['contactNumber'] ?? '');
        $candidate->setKeywords($data['keywords'] ?? '');
        $candidate->setComment($data['comment'] ?? '');
        $candidate->setModeOfApplication(Candidate::MODE_OF_APPLICATION_MANUAL);
        $candidate->setConsentToKeepData(true);
        
        if (!empty($data['dateOfApplication'])) {
            $candidate->setDateOfApplication(new DateTime($data['dateOfApplication']));
        } else {
            $candidate->setDateOfApplication($this->getDateTimeHelper()->getNow());
        }

        // Set the person who added the candidate
        $candidate->getDecorator()->setAddedPersonById($this->getAuthUser()->getEmpNumber());
        
        $candidate = $this->getCandidateService()->getCandidateDao()->saveCandidate($candidate);

        // If vacancy is specified, create candidate-vacancy relationship
        if ($vacancyId) {
            $candidateVacancy = new CandidateVacancy();
            $candidateVacancy->getDecorator()->setCandidateById($candidate->getId());
            $candidateVacancy->getDecorator()->setVacancyById($vacancyId);
            $candidateVacancy->setStatus(CandidateService::STATUS_MAP[WorkflowStateMachine::RECRUITMENT_APPLICATION_ACTION_ATTACH_VACANCY]);
            $candidateVacancy->setAppliedDate($candidate->getDateOfApplication());
            
            $this->getCandidateService()->getCandidateDao()->saveCandidateVacancy($candidateVacancy);

            // Create history entry
            $candidateHistory = new CandidateHistory();
            $candidateHistory->getDecorator()->setCandidateById($candidate->getId());
            $candidateHistory->getDecorator()->setVacancyById($vacancyId);
            $candidateHistory->setAction(WorkflowStateMachine::RECRUITMENT_APPLICATION_ACTION_ATTACH_VACANCY);
            $candidateHistory->setPerformedDate($this->getDateTimeHelper()->getNow());
            $candidateHistory->getDecorator()->setPerformedBy($this->getAuthUser()->getEmpNumber());
            $candidateHistory->setNote('Candidate added via bulk upload');
            
            $this->getCandidateService()->getCandidateDao()->saveCandidateHistory($candidateHistory);
        }

        return $candidate;
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                self::PARAMETER_CANDIDATES,
                new Rule(Rules::ARRAY_TYPE)
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::PARAMETER_VACANCY_ID,
                    new Rule(Rules::POSITIVE)
                )
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function delete(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function update(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}
