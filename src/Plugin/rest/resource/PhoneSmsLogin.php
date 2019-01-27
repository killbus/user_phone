<?php

namespace Drupal\user_phone\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\phone_verify\SmsCodeVerifierInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\simple_oauth_code\AuthorizationCodeGeneratorInterface;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "user_phone_phone_sms_login",
 *   label = @Translation("Phone sms login"),
 *   uri_paths = {
 *     "create" = "/api/rest/user-phone/phone-sms-login"
 *   }
 * )
 */
class PhoneSmsLogin extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var SmsCodeVerifierInterface
   */
  protected $smsCodeVerifier;

  /**
   * Constructs a new PhoneSmsLogin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    SmsCodeVerifierInterface $sms_code_verifier) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->smsCodeVerifier = $sms_code_verifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('user_phone'),
      $container->get('current_user'),
      $container->get('phone_verify.sms_code_verifier')
    );
  }

  public function permissions() {
    return [];
  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data) {
    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if ($this->smsCodeVerifier->verify($data['phone'], $data['code'])) {
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['phone' => $data['phone']]);
      /** @var AuthorizationCodeGeneratorInterface $generator */
      $generator = \Drupal::getContainer()->get('simple_oauth_code.authorization_code_generator');
      if (count($users)) {
        /** @var AccountInterface $user */
        $user = array_pop($users);
        // 已经注册，生成 simple_oauth code
        $authorization = $generator->generate($data['client_id'], $user);
      } elseif (isset($data['auto_register']) && (bool)$data['auto_register']) {
        // 还没注册，自动注册创建新用户
        $user = $this->createUser($data['phone'], $data['phone']. '@' .$data['phone']);
        $user->set('phone', $data['phone']);
        $user->save();
        $authorization = $generator->generate($data['client_id'], $user);
      } else {
        // 提示手机号没有注册
        throw new BadRequestHttpException('该手机号还没有注册');
      }
    }

    return new ModifiedResourceResponse([
      'authorization' => $authorization
    ], 200);
  }

  /**
   * @param $username
   * @param $email
   * @return User
   */
  protected function createUser($username, $email) {
    return \Drupal::getContainer()->get('enhanced_user.user_creator')->createUser($username, $email);
  }
}
