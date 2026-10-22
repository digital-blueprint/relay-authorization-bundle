<?php

declare(strict_types=1);

namespace Dbp\Relay\AuthorizationBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\Entity\UserGroup;
use Dbp\Relay\AuthorizationBundle\Entity\UserGroupMember;
use Dbp\Relay\AuthorizationBundle\Helper\UuidUtils;
use Dbp\Relay\AuthorizationBundle\Service\UserGroupService;
use Dbp\Relay\AuthorizationBundle\Tests\AbstractAuthorizationServiceTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class UserGroupServiceAuthorizationServiceTest extends AbstractAuthorizationServiceTestCase
{
    public function testAddGroup(): void
    {
        $userGroup = new UserGroup();
        $userGroup->setName(self::TEST_GROUP_NAME);

        $userGroup = $this->userGroupService->addUserGroup($userGroup);
        $groupPersistence = $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier());

        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($userGroup->getMembers());
    }

    public function testAddGroupInvalid(): void
    {
        $userGroup = new UserGroup();
        try {
            $this->userGroupService->addUserGroup($userGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddGroupInternalError(): void
    {
        $userGroup = new UserGroup();
        $userGroup->setName(self::TEST_GROUP_NAME);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->addUserGroup($userGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::ADDING_USER_GROUP_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testUpdateGroup(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $userGroup->setName(self::TEST_GROUP_NAME.'_updated');
        $this->userGroupService->updateUserGroup($userGroup);

        $groupPersistence = $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME.'_updated', $groupPersistence->getName());
    }

    public function testUpdateGroupInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $userGroup->setName(self::TEST_GROUP_NAME.'_updated');

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->updateUserGroup($userGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::UPDATING_USER_GROUP_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testRemoveGroup(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $this->userGroupService->removeUserGroup($userGroup);
        $this->assertNull($this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier()));
    }

    public function testRemoveGroupInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->removeUserGroup($userGroup);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::REMOVING_USER_GROUP_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testRemoveParentGroup(): void
    {
        // test ON DELETE CASCADE for 'group' (parent group)
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals($groupMember->getIdentifier(),
            $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier())->getIdentifier());

        $this->userGroupService->removeUserGroup($userGroup);
        $this->assertNull($this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier()));
        $this->assertNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));
    }

    public function testRemoveChildGroup(): void
    {
        // test ON DELETE CASCADE for 'childGroup' (= group that is a member of another group)
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());
        $childGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_2');
        $this->assertEquals($childGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($childGroup->getIdentifier())->getIdentifier());

        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, null, $childGroup);
        $this->assertNotNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));

        $this->userGroupService->removeUserGroup($childGroup);
        $this->assertNotNull($this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier()));
        $this->assertNull($this->testEntityManager->getUserGroupByIdentifier($childGroup->getIdentifier()));
        $this->assertNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));
    }

    public function testRemoveResourceActionGrantGroup(): void
    {
        // test ON DELETE CASCADE for ResourceActionGrant::group (= group that holds a grant)
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $this->assertEquals($userGroup->getIdentifier(), $this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier())->getIdentifier());

        $resourceActionGrant = $this->testEntityManager->addAuthorizationResourceAndActionGrant(
            TestEntityManager::DEFAULT_RESOURCE_CLASS,
            TestEntityManager::DEFAULT_RESOURCE_IDENTIFIER,
            action: 'read',
            userGroup: $userGroup
        );
        $this->assertEquals($resourceActionGrant->getIdentifier(),
            $this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier())->getIdentifier());

        $this->userGroupService->removeUserGroup($userGroup);
        $this->assertNull($this->testEntityManager->getUserGroupByIdentifier($userGroup->getIdentifier()));
        $this->assertNull($this->testEntityManager->getResourceActionGrantByIdentifier($resourceActionGrant->getIdentifier()));
    }

    public function testTryGetGroupItem(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);

        $groupPersistence = $this->userGroupService->tryGetUserGroupByIdentifier($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($userGroup->getMembers());
    }

    public function testTryGetGroupItemNotFound(): void
    {
        $this->assertNull($this->userGroupService->tryGetUserGroupByIdentifier(Uuid::v7()->toRfc4122()));
    }

    public function testTryGetGroupItemNotFoundInvalidId(): void
    {
        $this->assertNull($this->userGroupService->tryGetUserGroupByIdentifier('404'));
    }

    public function testGetGroupItem(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);

        $groupPersistence = $this->userGroupService->getUserGroupByIdentifier($userGroup->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupPersistence->getName());
        $this->assertEmpty($userGroup->getMembers());
    }

    public function testGetGroupItemNotFound(): void
    {
        try {
            $this->userGroupService->getUserGroupByIdentifier(Uuid::v7()->toRfc4122());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $e) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $e->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_NOT_FOUND_ERROR_ID, $e->getErrorId());
        }
    }

    public function testGetGroupItemNotFoundInvalidId(): void
    {
        try {
            $this->userGroupService->getUserGroupByIdentifier('404');
            $this->fail('exception not thrown as expected');
        } catch (ApiError $e) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $e->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_NOT_FOUND_ERROR_ID, $e->getErrorId());
        }
    }

    public function testGetGroupItemInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->getUserGroupByIdentifier($userGroup->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::GETTING_USER_GROUP_ITEM_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddGroupMemberWithUserIdentifier(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $groupMember = $this->userGroupService->addUserGroupMember($groupMember);

        $groupMemberPersistence = $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupMemberPersistence->getUserGroup()->getIdentifier());
        $this->assertEquals(self::CURRENT_USER_IDENTIFIER, $groupMemberPersistence->getUserIdentifier());
    }

    public function testAddGroupMemberWithChildGroup(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $childGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_2');
        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setChildGroup($childGroup);
        $groupMember = $this->userGroupService->addUserGroupMember($groupMember);

        $groupMemberPersistence = $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals($userGroup->getIdentifier(), $groupMemberPersistence->getUserGroup()->getIdentifier());
        $this->assertEquals(null, $groupMemberPersistence->getUserIdentifier());
        $this->assertEquals($childGroup->getIdentifier(), $groupMemberPersistence->getChildGroup()->getIdentifier());
    }

    public function testAddGroupMemberInvalidGroupNull(): void
    {
        $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = new UserGroupMember();
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    /**
     * Matching parent and child group would cause and endless loop.
     */
    public function testAddGroupMemberInvalidGroupAndChildGroupMatch(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setChildGroup($userGroup);

        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    /**
     * Matching parent and child group would cause and endless loop.
     */
    public function testAddGroupMemberInvalidChildGroupIsAncestorOfGroup(): void
    {
        $group0 = $this->testEntityManager->addUserGroup();
        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $group3 = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addUserGroupMember($group0, null, $group2);
        $this->testEntityManager->addUserGroupMember($group1, null, $group2);
        $this->testEntityManager->addUserGroupMember($group2, null, $group3);
        $this->testEntityManager->addUserGroupMember($group3, self::CURRENT_USER_IDENTIFIER);

        $groupMember = new UserGroupMember();

        $groupMember->setUserGroup($group3);
        $groupMember->setChildGroup($group0);
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setUserGroup($group3);
        $groupMember->setChildGroup($group1);
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setUserGroup($group3);
        $groupMember->setChildGroup($group2);
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setUserGroup($group2);
        $groupMember->setChildGroup($group0);
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        $groupMember->setUserGroup($group2);
        $groupMember->setChildGroup($group1);
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }

        // OK:
        $groupMember->setUserGroup($group1);
        $groupMember->setChildGroup($group0);
        $this->userGroupService->addUserGroupMember($groupMember);
    }

    /**
     * Matching parent and child group would cause and endless loop.
     */
    public function testAddGroupMemberInvalidChildGroupAlreadyAdded(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $childGroup = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addUserGroupMember($userGroup, null, $childGroup);

        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setChildGroup($childGroup);
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddGroupMemberInvalidBothUserAndChildGroupSet(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $childGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME.'_2');
        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);
        $groupMember->setChildGroup($childGroup);

        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddGroupMemberInvalidNeitherUserNorChildGroupSet(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);

        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_INVALID_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddGroupMemberInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = new UserGroupMember();
        $groupMember->setUserGroup($userGroup);
        $groupMember->setUserIdentifier(self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->addUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::ADDING_USER_GROUP_MEMBER_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testRemoveGroupMember(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals($groupMember->getIdentifier(),
            $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier())->getIdentifier());
        $this->userGroupService->removeUserGroupMember($groupMember);
        $this->assertNull($this->testEntityManager->getUserGroupMember($groupMember->getIdentifier()));
    }

    public function testRemoveGroupMemberInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $this->assertEquals($groupMember->getIdentifier(),
            $this->testEntityManager->getUserGroupMember($groupMember->getIdentifier())->getIdentifier());

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->removeUserGroupMember($groupMember);
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::REMOVING_USER_GROUP_MEMBER_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testGetGroupMemberItem(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $groupMemberPersistence = $this->userGroupService->getUserGroupMemberByIdentifier($groupMember->getIdentifier());
        $this->assertEquals($groupMember->getIdentifier(), $groupMemberPersistence->getIdentifier());
        $this->assertEquals(self::TEST_GROUP_NAME, $groupMemberPersistence->getUserGroup()->getName());
    }

    public function testGetGroupMemberItemNotFound(): void
    {
        try {
            $this->userGroupService->getUserGroupMemberByIdentifier(Uuid::v7()->toRfc4122());
            $this->fail('Expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_NOT_FOUND_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testGetGroupMemberNotFoundInvalidId(): void
    {
        try {
            $this->userGroupService->getUserGroupMemberByIdentifier('404');
            $this->fail('Expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::USER_GROUP_MEMBER_NOT_FOUND_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testGetGroupMemberItemInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $groupMember = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->getUserGroupMemberByIdentifier($groupMember->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::GETTING_USER_GROUP_MEMBER_ITEM_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testGetGroupMemberCollection(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);
        $subGroup = $this->testEntityManager->addUserGroup('subgroup');
        $groupMembers = $this->userGroupService->getUserGroupMembers(1, 10, $userGroup->getIdentifier());
        $this->assertCount(0, $groupMembers);

        $subgroupMember = $this->testEntityManager->addUserGroupMember($subGroup, self::CURRENT_USER_IDENTIFIER.'_2');
        $groupMember1 = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);
        $groupMember2 = $this->testEntityManager->addUserGroupMember($userGroup, null, $subGroup);
        $groupMember3 = $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER.'_3');

        $groupMembers = $this->userGroupService->getUserGroupMembers(0, 10, $subGroup->getIdentifier());
        $this->assertCount(1, $groupMembers);
        $this->assertEquals($subgroupMember->getIdentifier(), $groupMembers[0]->getIdentifier());

        $groupMembers = $this->userGroupService->getUserGroupMembers(0, 10, $userGroup->getIdentifier());
        $this->assertCount(3, $groupMembers);
        $this->assertEquals($groupMember1->getIdentifier(), $groupMembers[0]->getIdentifier());
        $this->assertEquals($groupMember2->getIdentifier(), $groupMembers[1]->getIdentifier());
        $this->assertEquals($groupMember3->getIdentifier(), $groupMembers[2]->getIdentifier());

        $groupMembers = $this->userGroupService->getUserGroupMembers(0, 2, $userGroup->getIdentifier());
        $this->assertCount(2, $groupMembers);
        $this->assertEquals($groupMember1->getIdentifier(), $groupMembers[0]->getIdentifier());
        $this->assertEquals($groupMember2->getIdentifier(), $groupMembers[1]->getIdentifier());

        $groupMembers = $this->userGroupService->getUserGroupMembers(2, 2, $userGroup->getIdentifier());
        $this->assertCount(1, $groupMembers);
        $this->assertEquals($groupMember3->getIdentifier(), $groupMembers[0]->getIdentifier());

        $groupMembers = $this->userGroupService->getUserGroupMembers(4, 2, $userGroup->getIdentifier());
        $this->assertCount(0, $groupMembers);
    }

    public function testGetGroupMemberCollectionInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup(self::TEST_GROUP_NAME);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->getUserGroupMembers(0, 10, $userGroup->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
            $this->assertEquals(UserGroupService::GETTING_USER_GROUP_MEMBER_COLLECTION_FAILED_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testIsUserMemberOfUserGroup(): void
    {
        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $subGroup1 = $this->testEntityManager->addUserGroup();
        $subGroup2 = $this->testEntityManager->addUserGroup();
        $subSubGroup1 = $this->testEntityManager->addUserGroup();
        $groupEmpty = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addUserGroupMember($subSubGroup1, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addUserGroupMember($subGroup1, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addUserGroupMember($subGroup1, null, $subSubGroup1);

        $this->testEntityManager->addUserGroupMember($subGroup2, self::CURRENT_USER_IDENTIFIER.'_4');

        $this->testEntityManager->addUserGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addUserGroupMember($group1, null, $subGroup1);
        $this->testEntityManager->addUserGroupMember($group1, null, $subGroup2);

        $this->testEntityManager->addUserGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_4');
        $this->testEntityManager->addUserGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addUserGroupMember($group2, null, $subGroup1);

        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $group1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_2', $group1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_3', $group1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_5', $group1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_6', $group1->getIdentifier()));

        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $group2->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_2', $group2->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_3', $group2->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group2->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_5', $group2->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_6', $group2->getIdentifier()));

        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $subGroup1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subGroup1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subGroup1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_4', $group1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_5', $subGroup1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_6', $subGroup1->getIdentifier()));

        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $subGroup2->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subGroup2->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subGroup2->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_4', $subGroup2->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_5', $subGroup2->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_6', $subGroup2->getIdentifier()));

        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_2', $subSubGroup1->getIdentifier()));
        $this->assertTrue($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_3', $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_4', $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_5', $subSubGroup1->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_6', $subSubGroup1->getIdentifier()));

        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $groupEmpty->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_2', $groupEmpty->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_3', $groupEmpty->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_4', $groupEmpty->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_5', $groupEmpty->getIdentifier()));
        $this->assertFalse($this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER.'_6', $groupEmpty->getIdentifier()));
    }

    public function testIsUserMemberOfUserGroupInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->isUserMemberOfUserGroup(self::CURRENT_USER_IDENTIFIER, $userGroup->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
        }
    }

    public function testGetMembersOfUserGroup(): void
    {
        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $subGroup1 = $this->testEntityManager->addUserGroup();
        $subGroup2 = $this->testEntityManager->addUserGroup();
        $subSubGroup1 = $this->testEntityManager->addUserGroup();
        $groupEmpty = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addUserGroupMember($subSubGroup1, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addUserGroupMember($subGroup1, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addUserGroupMember($subGroup1, null, $subSubGroup1);

        $this->testEntityManager->addUserGroupMember($subGroup2, self::CURRENT_USER_IDENTIFIER.'_4');

        $this->testEntityManager->addUserGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addUserGroupMember($group1, null, $subGroup1);
        $this->testEntityManager->addUserGroupMember($group1, null, $subGroup2);

        $this->testEntityManager->addUserGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_4');
        $this->testEntityManager->addUserGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_5');
        $this->testEntityManager->addUserGroupMember($group2, null, $subGroup1);

        $this->assertEmpty($this->userGroupService->getMembersOfUserGroup($groupEmpty->getIdentifier()));

        $this->assertIsPermutationOf([
            self::CURRENT_USER_IDENTIFIER,
            self::CURRENT_USER_IDENTIFIER.'_2',
            self::CURRENT_USER_IDENTIFIER.'_3',
            self::CURRENT_USER_IDENTIFIER.'_4',
        ], $this->userGroupService->getMembersOfUserGroup($group1->getIdentifier()));

        $this->assertIsPermutationOf([
            self::CURRENT_USER_IDENTIFIER.'_2',
            self::CURRENT_USER_IDENTIFIER.'_3',
            self::CURRENT_USER_IDENTIFIER.'_4',
            self::CURRENT_USER_IDENTIFIER.'_5',
        ], $this->userGroupService->getMembersOfUserGroup($group2->getIdentifier()));

        $this->assertIsPermutationOf([
            self::CURRENT_USER_IDENTIFIER.'_2',
            self::CURRENT_USER_IDENTIFIER.'_3',
        ], $this->userGroupService->getMembersOfUserGroup($subGroup1->getIdentifier()));

        $this->assertIsPermutationOf([
            self::CURRENT_USER_IDENTIFIER.'_4',
        ], $this->userGroupService->getMembersOfUserGroup($subGroup2->getIdentifier()));

        $this->assertIsPermutationOf([
            self::CURRENT_USER_IDENTIFIER.'_3',
        ], $this->userGroupService->getMembersOfUserGroup($subSubGroup1->getIdentifier()));
    }

    public function testGetMembersOfUserGroupInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->getMembersOfUserGroup($userGroup->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
        }
    }

    public function testGetGroupsUserIsMemberOf(): void
    {
        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $subGroup1 = $this->testEntityManager->addUserGroup();
        $subGroup2 = $this->testEntityManager->addUserGroup();
        $subSubGroup1 = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addUserGroupMember($subSubGroup1, self::CURRENT_USER_IDENTIFIER.'_3');

        $this->testEntityManager->addUserGroupMember($subGroup1, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->testEntityManager->addUserGroupMember($subGroup1, null, $subSubGroup1);

        $this->testEntityManager->addUserGroupMember($subGroup2, self::CURRENT_USER_IDENTIFIER.'_4');

        $this->testEntityManager->addUserGroupMember($group1, self::CURRENT_USER_IDENTIFIER);
        $this->testEntityManager->addUserGroupMember($group1, null, $subGroup1);
        $this->testEntityManager->addUserGroupMember($group1, null, $subGroup2);

        $this->testEntityManager->addUserGroupMember($group2, null, $subGroup1);
        $this->testEntityManager->addUserGroupMember($group2, self::CURRENT_USER_IDENTIFIER.'_3');

        $groups = $this->userGroupService->getUserGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER);
        $this->assertCount(1, $groups);
        $this->assertEquals($group1->getIdentifier(), $groups[0]);

        $groups = $this->userGroupService->getUserGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_2');
        $this->assertCount(3, $groups);
        $this->assertContains($group1->getIdentifier(), $groups);
        $this->assertContains($subGroup1->getIdentifier(), $groups);
        $this->assertContains($group2->getIdentifier(), $groups);

        $groups = $this->userGroupService->getUserGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_3');
        $this->assertCount(4, $groups);
        $this->assertContains($group1->getIdentifier(), $groups);
        $this->assertContains($subGroup1->getIdentifier(), $groups);
        $this->assertContains($subSubGroup1->getIdentifier(), $groups);
        $this->assertContains($group2->getIdentifier(), $groups);

        $groups = $this->userGroupService->getUserGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_4');
        $this->assertCount(2, $groups);
        $this->assertContains($group1->getIdentifier(), $groups);
        $this->assertContains($subGroup2->getIdentifier(), $groups);

        $groups = $this->userGroupService->getUserGroupsUserIsMemberOf(self::CURRENT_USER_IDENTIFIER.'_5');
        $this->assertCount(0, $groups);
    }

    public function testGetDisallowedChildGroupIdentifiersFor(): void
    {
        // all ancestors of the group, all child groups, and the group itself are disallowed group members
        $group0 = $this->testEntityManager->addUserGroup();
        $group1 = $this->testEntityManager->addUserGroup();
        $group2 = $this->testEntityManager->addUserGroup();
        $group3 = $this->testEntityManager->addUserGroup();

        $this->testEntityManager->addUserGroupMember($group0, null, $group2);
        $this->testEntityManager->addUserGroupMember($group1, null, $group2);
        $this->testEntityManager->addUserGroupMember($group2, null, $group3);
        $this->testEntityManager->addUserGroupMember($group3, self::CURRENT_USER_IDENTIFIER);

        $this->assertIsPermutationOf(UuidUtils::toBinaryUuids(
            [$group0->getIdentifier(), $group2->getIdentifier()]),
            $this->userGroupService->getDisallowedChildGroupIdentifiersBinaryFor($group0->getIdentifier()));
        $this->assertIsPermutationOf(UuidUtils::toBinaryUuids(
            [$group1->getIdentifier(), $group2->getIdentifier()]),
            $this->userGroupService->getDisallowedChildGroupIdentifiersBinaryFor($group1->getIdentifier()));
        $this->assertIsPermutationOf(UuidUtils::toBinaryUuids(
            [$group0->getIdentifier(), $group1->getIdentifier(), $group2->getIdentifier(), $group3->getIdentifier()]),
            $this->userGroupService->getDisallowedChildGroupIdentifiersBinaryFor($group2->getIdentifier()));
        $this->assertIsPermutationOf(UuidUtils::toBinaryUuids(
            [$group0->getIdentifier(), $group1->getIdentifier(), $group2->getIdentifier(), $group3->getIdentifier()]),
            $this->userGroupService->getDisallowedChildGroupIdentifiersBinaryFor($group3->getIdentifier()));
    }

    public function testGetDisallowedChildGroupIdentifiersForInternalError(): void
    {
        $userGroup = $this->testEntityManager->addUserGroup();
        $this->testEntityManager->addUserGroupMember($userGroup, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager->prepareDBError();
        try {
            $this->userGroupService->getDisallowedChildGroupIdentifiersBinaryFor($userGroup->getIdentifier());
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
        }
    }
}
