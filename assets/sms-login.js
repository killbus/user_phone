(function ($) {
  Drupal.behaviors.sms_login = {
    attach: function (context, settings) {
      if ($('input[name=use_sms_login]:checked').length) {
        $login_btn = $('form.user-phone-login-form .form-actions input[name=op]');
        $login_btn.css('display', 'none');
        if ($('.form-item.verified.show').length) {
          $('.logging-in-message').css('display', 'block');
          $login_btn.click()
        }
      }
    }
  };

})(jQuery);
