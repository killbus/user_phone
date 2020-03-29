<?php
namespace Drupal\user_phone\Oauth2;

use Drupal\phone_verify\SmsCodeVerifierInterface;
use Drupal\simple_oauth\Entities\UserEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SMS grant class.
 */
class SMSGrant extends PasswordGrant {

  /**
   * @param ServerRequestInterface $request
   * @param ClientEntityInterface $client
   *
   * @return UserEntityInterface
   * @throws OAuthServerException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client)
  {
    $country = $this->getRequestParameter('country', $request);
    if (is_null($country)) {
      throw OAuthServerException::invalidRequest('country');
    }

    $number = $this->getRequestParameter('number', $request);
    if (is_null($number)) {
      throw OAuthServerException::invalidRequest('number');
    }

    $code = $this->getRequestParameter('code', $request);
    if (is_null($code)) {
      throw OAuthServerException::invalidRequest('code');
    }

    /** @var \Drupal\mobile_number\MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $mobileNumber = $util->getMobileNumber($number, $country);
    if ($mobileNumber) {

      // 查找手机号是否已经注册
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['phone' => $util->getCallableNumber($mobileNumber)]);
      if (count($users) > 1) {
        // 多个用户，提示找管理员
        throw OAuthServerException::invalidRequest('number', t('Multi accounts found for the mobile number, please contact administrator.'));
      } elseif (count($users) === 0) {
        // 找不到用户
        throw OAuthServerException::invalidRequest('number', t('Phone number not exist.'));
      }

      $user = new UserEntity();
      $user->setIdentifier(reset($users)->id());

      /** @var SmsCodeVerifierInterface $phone_verify */
      $phone_verify = \Drupal::service('phone_verify.sms_code_verifier');
      if ($phone_verify->verify($util->getCallableNumber($mobileNumber), $code)) {
        return $user;
      }
    }

    $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));
    throw OAuthServerException::invalidCredentials();
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifier()
  {
    return 'sms';
  }
}
