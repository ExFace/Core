-- UP

UPDATE exf_auth_point SET class = '\exface\Core\CommonLogic\Security\Authorization\HttpRequestAuthorizationPoint' WHERE class = '\exface\Core\CommonLogic\Security\Authorization\FacadeAuthorizationPoint';
UPDATE exf_auth_point SET policy_prototype_class = '\exface\Core\CommonLogic\Security\Authorization\HttpRequestAuthorizationPolicy' WHERE policy_prototype_class = '\exface\Core\CommonLogic\Security\Authorization\FacadeAuthorizationPolicy';

	
-- DOWN

UPDATE exf_auth_point SET class = '\exface\Core\CommonLogic\Security\Authorization\FacadeAuthorizationPoint' WHERE class = '\exface\Core\CommonLogic\Security\Authorization\HttpRequestAuthorizationPoint';
UPDATE exf_auth_point SET policy_prototype_class = '\exface\Core\CommonLogic\Security\Authorization\FacadeAuthorizationPolicy' WHERE policy_prototype_class = '\exface\Core\CommonLogic\Security\Authorization\HttpRequestAuthorizationPolicy';
UPDATE exf_auth_point SET class = '\exface\Core\CommonLogic\Security\Authorization\FacadeAuthorizationPoint' WHERE class = '\exface\Core\CommonLogic\Security\Authorization\CommandLineAuthorizationPoint';
UPDATE exf_auth_point SET policy_prototype_class = '\exface\Core\CommonLogic\Security\Authorization\FacadeAuthorizationPolicy' WHERE policy_prototype_class = '\exface\Core\CommonLogic\Security\Authorization\CommandLineAuthorizationPolicy';
