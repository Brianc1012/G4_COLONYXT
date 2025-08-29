<?php
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\PluginConfigurationInterface;
use OrangeHRM\Core\Traits\ORM\EntityManagerTrait;
use OrangeHRM\Entity\ApiPermission;
use OrangeHRM\Entity\DataGroup;
use OrangeHRM\Entity\DataGroupPermission;
use OrangeHRM\Entity\Module;
use OrangeHRM\Entity\UserRole;

class AssistantPluginConfiguration implements PluginConfigurationInterface
{
    use EntityManagerTrait;

    public function initialize(Request $request): void
    {
        // Ensure API/DataGroup permissions exist for the Assistant endpoint to avoid 403.
        // This is idempotent and runs on boot; safe if records already exist.
        $em = $this->getEntityManager();

        $apiClass = 'OrangeHRM\\Assistant\\Api\\AssistantChatAPI';
        $dataGroupName = 'apiv2_assistant_chat';
        $dataGroupDesc = 'Assistant - Chat';
        $moduleName = 'general'; // fall back to 'admin' if not present

        // Lookup ApiPermission by api_name
        $conn = $em->getConnection();
        $existingApi = $conn->createQueryBuilder()
            ->select('id')
            ->from('ohrm_api_permission')
            ->andWhere('api_name = :api')
            ->setParameter('api', $apiClass)
            ->setMaxResults(1)
            ->fetchOne();

        if (!$existingApi) {
            $em->beginTransaction();
            try {
                // Find or create DataGroup
                $dgId = $conn->createQueryBuilder()
                    ->select('id')
                    ->from('ohrm_data_group')
                    ->andWhere('name = :name')
                    ->setParameter('name', $dataGroupName)
                    ->setMaxResults(1)
                    ->fetchOne();

                if (!$dgId) {
                    $conn->insert('ohrm_data_group', [
                        'name' => $dataGroupName,
                        'description' => $dataGroupDesc,
                        'can_read' => 0,
                        'can_create' => 1,
                        'can_update' => 0,
                        'can_delete' => 0,
                    ]);
                    $dgId = (int)$conn->lastInsertId();
                }

                // Resolve module id; prefer 'general', fallback to 'admin'
                $moduleId = $conn->createQueryBuilder()
                    ->select('id')
                    ->from('ohrm_module')
                    ->andWhere('name = :name')
                    ->setParameter('name', $moduleName)
                    ->setMaxResults(1)
                    ->fetchOne();
                if (!$moduleId) {
                    $moduleId = $conn->createQueryBuilder()
                        ->select('id')
                        ->from('ohrm_module')
                        ->andWhere('name = :name')
                        ->setParameter('name', 'admin')
                        ->setMaxResults(1)
                        ->fetchOne();
                }

                // Create ApiPermission row
                $conn->insert('ohrm_api_permission', [
                    'api_name' => $apiClass,
                    'module_id' => $moduleId,
                    'data_group_id' => $dgId,
                ]);

                // Grant create permission to ESS and Supervisor roles
                $grantRoles = ['ESS', 'Supervisor'];
                foreach ($grantRoles as $roleName) {
                    $roleId = $conn->createQueryBuilder()
                        ->select('id')
                        ->from('ohrm_user_role')
                        ->andWhere('name = :name')
                        ->setParameter('name', $roleName)
                        ->setMaxResults(1)
                        ->fetchOne();
                    if ($roleId) {
                        // Check existing row
                        $exists = $conn->createQueryBuilder()
                            ->select('id')
                            ->from('ohrm_user_role_data_group')
                            ->andWhere('data_group_id = :dg')
                            ->andWhere('user_role_id = :ur')
                            ->setParameter('dg', $dgId)
                            ->setParameter('ur', $roleId)
                            ->setMaxResults(1)
                            ->fetchOne();
                        if (!$exists) {
                            $conn->insert('ohrm_user_role_data_group', [
                                'data_group_id' => $dgId,
                                'user_role_id' => $roleId,
                                'can_read' => 0,
                                'can_create' => 1,
                                'can_update' => 0,
                                'can_delete' => 0,
                                'self' => 1,
                            ]);
                        }
                    }
                }

                $em->commit();
            } catch (\Throwable $e) {
                $em->rollback();
                // Swallow to avoid boot failure; logs can be added if a logger is available.
            }
        }
    }
}
