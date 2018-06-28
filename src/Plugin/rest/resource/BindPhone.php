<?php

namespace Drupal\phone_login\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "phone_login_bind_phone",
 *   label = @Translation("Bind phone"),
 *   uri_paths = {
 *     "create" = "/api/rest/phone-login/bind-phone"
 *   }
 * )
 */
class BindPhone extends ResourceBase
{

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
        AccountProxyInterface $current_user)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

        $this->currentUser = $current_user;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->getParameter('serializer.formats'),
            $container->get('logger.factory')->get('phone_login'),
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
    public function post($data)
    {
        // You must to implement the logic of your REST Resource here.
        // Use current user after pass authentication to validate access.
        if (!$this->currentUser->hasPermission('access content')) {
            throw new AccessDeniedHttpException();
        }

        if (!isset($data['user_id']) || !isset($data['phone']))
            throw new ParameterNotFoundException('user_id and phone are required.');

        if ((int)$this->currentUser->id() !== (int)$data['user_id'])
            throw new AccessDeniedHttpException('只能修改自己的手机.');

        $user = User::load($this->currentUser->id());
        if ($user instanceof User) {
            $user->set('phone', $data['phone']);
            $user->save();
        }

        return new ModifiedResourceResponse($user, 200);
    }
}
