<?php
namespace exface\Core\Interfaces\Security;

/**
 * Interface for jwt bearer based authentication tokens.
 *
 * @author Sergej Riel
 *
 */
interface JWTAuthenticationTokenInterface extends AuthenticationTokenInterface
{
    /**
     *
     * @return string
     */
    public function getJWTToken() : string;
}