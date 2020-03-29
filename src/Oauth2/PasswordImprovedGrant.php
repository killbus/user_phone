<?php
namespace Drupal\user_phone\Oauth2;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Password Improved grant class.
 */
class PasswordImprovedGrant extends PasswordGrant {
  /**
   * @param ServerRequestInterface $request
   * @param ClientEntityInterface  $client
   *
   * @throws OAuthServerException
   *
   * @return UserEntityInterface
   */
  protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client)
  {
    $username = $this->getRequestParameter('username', $request);
    if (is_null($username)) {
      throw OAuthServerException::invalidRequest('username');
    }

    $password = $this->getRequestParameter('password', $request);
    if (is_null($password)) {
      throw OAuthServerException::invalidRequest('password');
    }

    // Transfer email address and phone number to username
    $login_input = $username;
    if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
      $user = user_load_by_mail($login_input);
      if ($user) {
        $username = $user->getAccountName();
      }
    } else {
      try {
        if ($user_name = user_phone_transfer_phone_to_username($login_input)) {
          $username = $user_name;
        }
      } catch (\Exception $e) {
        throw OAuthServerException::invalidRequest('username', $e->getMessage());
      }
    }

    $user = $this->userRepository->getUserEntityByUserCredentials(
      $username,
      $password,
      $this->getIdentifier(),
      $client
    );
    if ($user instanceof UserEntityInterface === false) {
      $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

      throw OAuthServerException::invalidCredentials();
    }

    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifier()
  {
    return 'password_improved';
  }
}
