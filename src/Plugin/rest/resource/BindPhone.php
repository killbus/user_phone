<?php

namespace Drupal\user_phone\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;
use phpDocumentor\Reflection\Types\Array_;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "user_phone_bind_phone",
 *   label = @Translation("Bind phone"),
 *   uri_paths = {
 *     "create" = "/api/rest/user-phone/bind-phone"
 *   }
 * )
 */
class BindPhone extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new BindPhone object.
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
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
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
      $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param $data
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post($data) {

    if (!isset($data['user_id']) || !isset($data['phone']))
      throw new ParameterNotFoundException('user_id and phone are required.');

    if ((int)$this->currentUser->id() !== (int)$data['user_id'])
      throw new AccessDeniedHttpException('只能修改自己的手机.');

    // 防止重复绑定
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
      'phone' => $data['phone']
    ]);

    if (count($users)) {
      $user = array_pop($users);
      if ((int)$user->id() === (int)$data['user_id']) {
        throw new BadRequestHttpException('您已经绑定了此手机号');
      } else {
        throw new BadRequestHttpException('此手机号已经绑定了其他账号');
      }
    }

    $user = User::load($this->currentUser->id());
    if ($user instanceof User) {
      $user->set('phone', $data['phone']);
      $user->save();
    }

    return new ModifiedResourceResponse($user, 200);
  }
}
