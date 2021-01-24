# Allow your users to login with FaceID/TouchID

[![Latest Version on Packagist](https://img.shields.io/packagist/v/m1guelpf/laravel-fastlogin.svg?style=flat-square)](https://packagist.org/packages/m1guelpf/laravel-fastlogin)
[![Total Downloads](https://img.shields.io/packagist/dt/m1guelpf/laravel-fastlogin.svg?style=flat-square)](https://packagist.org/packages/m1guelpf/laravel-fastlogin)

Allow your users to register physical authentication devices (FaceID or TouchID on iPhones & macs, fingerprint on Android, Hello on Windows and USB keys) to skip entering their login credentials.

## Installation

You can install the package via composer:

```bash
composer require m1guelpf/laravel-fastlogin
```

## Usage

This package takes care of everything you need on the backend. To make our life easier on the frontend, we'll be using `@web-auth/webauthn-helper` and `js-cookie`. You can install them by running `yarn add @web-auth/webauthn-helper js-cookie`.

To get started, you need to have the user register a new credential. You can do so by presenting them with a modal when they login, or by adding the option to their settings page.

> Note: Due to Apple's restrictions, you can only call the creation function after a user gesture, that is, the function needs to be invoked in an user-generated event (like a "click" event).

```js
import Cookies from 'js-cookie'
import { useRegistration } from '@web-auth/webauthn-helper'

const onClick = () => {
    const token = Cookies.get('XSRF-TOKEN')

    useRegistration({
        actionUrl: route('fastlogin.create'),
        optionsUrl: route('fastlogin.create.details'),
        actionHeader: {
            'x-xsrf-token': token
        },
    }, {
        'x-xsrf-token': token
    })().then(() => {
        // credential has been added
    })
}
```

Then, on the login page, you should check if the user has a credential saved (you can do so by calling the `$request->hasCredential()` method) and, if so, displaying a button to sign in using FastLogin.

> Note: Due to Apple's restrictions, you can only call the login function after a user gesture, that is, the function needs to be invoked in an user-generated event (like a "click" event).

```js
import Cookies from 'js-cookie'
import { useLogin } from '@web-auth/webauthn-helper'

const onClick = () => {
    const token = Cookies.get('XSRF-TOKEN')

    useLogin({
        actionUrl: route('fastlogin.login'),
        optionsUrl: route('fastlogin.login.details'),
        actionHeader: {
            'x-xsrf-token': token
        },
    }, {
        'x-xsrf-token': token
    })().then(() => {
        // the user has been logged in

        window.location.reload()
    })
}
```

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Miguel Piedrafita](https://github.com/m1guelpf)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
