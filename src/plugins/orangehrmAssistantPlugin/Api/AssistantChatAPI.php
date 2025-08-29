<?php
namespace OrangeHRM\Assistant\Api;

use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Exception\NotImplementedException;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;

use OrangeHRM\Assistant\Service\AssistantService;

class AssistantChatAPI extends Endpoint implements CollectionEndpoint
{
    use AuthUserTrait;

    private ?AssistantService $assistantService = null;

    protected function getAssistantService(): AssistantService
    {
        if (!$this->assistantService instanceof AssistantService) {
            $this->assistantService = new AssistantService();
        }
        return $this->assistantService;
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule('message', new Rule(Rules::REQUIRED), new Rule(Rules::STRING_TYPE))
        );
    }

    public function create(): EndpointResult
    {
        $message = $this->getRequestParams()->getString(RequestParams::PARAM_TYPE_BODY, 'message');

        $empNumber = $this->getAuthUser()->getEmpNumber();
        $userRoleId = $this->getAuthUser()->getUserRoleId();

        $result = $this->getAssistantService()->handleMessage($message, $empNumber, $userRoleId);

        return new EndpointResourceResult(ArrayModel::class, $result);
    }

    // Unused CollectionEndpoint methods for this API
    public function getAll(): EndpointResult
    {
        throw new NotImplementedException();
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        throw new NotImplementedException();
    }

    public function delete(): EndpointResult
    {
        throw new NotImplementedException();
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw new NotImplementedException();
    }
}
