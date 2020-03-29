<?php

namespace Drupal\user_phone\Plugin\Oauth2Grant;

use Drupal\simple_oauth\Plugin\Oauth2Grant\Password;
use Drupal\user_phone\Oauth2\SMSGrant;

/**
 * @Oauth2Grant(
 *   id = "sms",
 *   label = @Translation("SMS")
 * )
 */
class SMS extends Password {

  /**
   * {@inheritdoc}
   */
  public function getGrantType() {
    $grant = new SMSGrant($this->userRepository, $this->refreshTokenRepository);
    $settings = $this->configFactory->get('simple_oauth.settings');
    $grant->setRefreshTokenTTL(new \DateInterval(sprintf('PT%dS', $settings->get('refresh_token_expiration'))));
    return $grant;
  }

}
