<?php

namespace Drupal\user_phone\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mobile_number\Element\MobileNumber;
use Drupal\mobile_number\MobileNumberUtilInterface;
use Drupal\user\Form\UserLoginForm;
use Drupal\user\UserInterface;

/**
 * Class LoginForm.
 */
class LoginForm extends UserLoginForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_phone_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#id'] = $this->getFormId();
    $form['phone_login'] = [
      '#type' => 'hidden',
      '#value' => 1
    ];
    $form['name']['#required'] = false;
    $form['name']['#states'] = [
      'invisible' => [
        'input[name="phone_login"]' => ['value' => '1']
      ],
      'optional' => [
        'input[name="phone_login"]' => ['value' => '1']
      ]
    ];

    $user_input = $form_state->getUserInput();

    /** @var \Drupal\mobile_number\Element\MobileNumberUtilInterface $util */
    $util = \Drupal::service('mobile_number.util');
    $submitted_value = empty($user_input['phone']) ? [] : $user_input['phone'];
    $form['phone'] = [
      '#type' => 'mobile_number',
      '#title' => t('Mobile Number'),
      '#default_value' => $submitted_value + [
        'value' => '',
        'country' => 'CN',
        'local_number' => '',
        'verified' => 0,
        'tfa' => 0,
      ],
      '#mobile_number' => [
        'verify' => empty($user_input['use_sms_login']) ? MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_NONE : MobileNumberUtilInterface::MOBILE_NUMBER_VERIFY_REQUIRED,
        'message' => MobileNumberUtilInterface::MOBILE_NUMBER_DEFAULT_SMS_MESSAGE,
        'tfa' => false,
        'token_data' => [],
        'placeholder' => $this->t('请输入手机号码')->render()
      ],
      '#pre_render' => [
        [$this, 'preRenderPhoneElement']
      ],
      '#weight' => -90
    ];

    $form['use_sms_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use SMS verification login'),
      '#ajax' => [
        'wrapper' => $form['#id'],
        'callback' => '::ajaxRefresh'
      ]
    ];

    if (!empty($user_input['use_sms_login'])) {
      $form['pass']['#required'] = false;
      $form['actions']['logging_in'] = [
        '#markup' => '<div class="logging-in-message">' . $this->t('Logging in, please wait ...') . '</div>'
      ];
      $form['#attached']['library'][] = 'user_phone/sms-login';
    }
    $form['pass']['#states'] = [
      'invisible' => [
        'input[name="use_sms_login"]' => ['checked' => true]
      ],
      'optional' => [
        'input[name="use_sms_login"]' => ['checked' => true]
      ]
    ];

    $form['#validate'] = array_merge([
      '::validateForm'
    ], $form['#validate']);

    return $form;
  }

  public function validateAuthentication(array &$form, FormStateInterface $form_state) {

    // 如果手机号格式验证通过
    $mobile_number = $form_state->getValue('phone');
    if (!empty($mobile_number['value'])) {

      // 查找手机号是否已经注册
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['phone' => $mobile_number]);
      if (count($users) > 1) {
        // 多个用户，提示找管理员
        $form_state->setError($form['phone'], 'Multi accounts found for the mobile number, please contact administrator.');
      } elseif (count($users) === 0) {
        // 手机号找不到用户
        $form_state->setError($form['phone'], 'No accounts found for the mobile number, maybe it is not register yet.');
      } else {
        // 找到一个用户

        /** @var UserInterface $user */
        $user = reset($users);

        if (!$form_state->getValue('use_sms_login')) {
          // 密码登录
          $form_state->setValue('name', $user->getAccountName());
          parent::validateAuthentication($form, $form_state);
        } else {
          // 短信登录

          $op = MobileNumber::getOp($form['phone'], $form_state);
          if ($op === 'mobile_number_verify') {
            // 如果点击的是短信验证按钮，验证短信码
            if (!$mobile_number['verified'] &&
              !empty($mobile_number['verification_token']) &&
              !empty($mobile_number['verification_code'])) {

              /** @var \Drupal\mobile_number\MobileNumberUtilInterface $util */
              $util = \Drupal::service('mobile_number.util');
              $mobileNumber = $util->getMobileNumber($mobile_number['value'], $mobile_number['country']);
              if (!$util->checkFlood($mobileNumber)) {
                $form_state->setError($form['phone'], 'Verification request too fast.');
              } else {
                if (!$util->verifyCode($mobileNumber, $mobile_number['verification_code'], $mobile_number['verification_token'])) {
                  $form_state->setError($form['phone'], 'Verification code fails.');
                } else {
                  // 验证成功，把手机号码字段设置为已验证
                  $mobile_number['verified'] = 1;
                  $form_state->setValue('phone', $mobile_number);
                }
              }
            }
          } elseif ($op === 'mobile_number_send_verification') {

          } else {

            if (!MobileNumber::isVerified($form['phone'])) {
              $form_state->setError($form['phone'], 'Please verify SMS code.');
            } else {
              // 已经验证过短信
              $form_state->set('uid', $user->id());
              $form_state->setValue('name', $user->getAccountName());

            }
          }

        }
      }
    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitForm($form, $form_state);
  }

  public function preRenderPhoneElement($element) {
    $element['label']['#required'] =
    $element['country-code']['#required'] =
    $element['mobile']['#required'] = true;
    return $element;
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('use_sms_login') && MobileNumber::isVerified($form['phone'])) {
      $response = new AjaxResponse();
      $response
        ->addCommand(new ReplaceCommand('#' . $form['#id'], $form))
        ->addCommand(new InvokeCommand('#' . $form['actions']['submit']['#id'], 'click'));
      return $response;
    } else return $form;
  }
}
