# user_phone

Provides a phone field to the core user entity.


## Features

- Provides `phone` base field to the core `user` entity.

- Provides REST plugins
  - `user_phone_bind_phone` 修改用户的绑定手机
  - `user_phone_phone_sms_login` 使用手机号码和短信验证码进行 Oauth2.0登录


## Dependencies

- [drupal/phone_verify](https://www.drupal.org/project/phone_verify) 模块，用于短讯验证码登录。
