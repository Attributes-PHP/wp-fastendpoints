# WP-FastEndpoints

<img src="https://raw.githubusercontent.com/Attributes-PHP/wp-fastendpoints/main/docs/images/wp-fastendpoints-wallpaper.png" alt="WordPress REST endpoints made easy">
<p align="center">
    <a href="https://github.com/Attributes-PHP/wp-fastendpoints/actions"><img alt="GitHub Actions Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/Attributes-PHP/wp-fastendpoints/tests.yml"></a>
    <a href="https://codecov.io/gh/Attributes-PHP/wp-fastendpoints" ><img alt="Code Coverage" src="https://codecov.io/gh/Attributes-PHP/wp-fastendpoints/graph/badge.svg?token=8N7N9NMGLG"/></a>
    <a href="https://packagist.org/packages/Attributes-PHP/wp-fastendpoints"><img alt="Latest Version" src="https://img.shields.io/packagist/v/Attributes-PHP/wp-fastendpoints"></a>
    <a href="https://packagist.org/packages/Attributes-PHP/wp-fastendpoints"><img alt="Supported WordPress Versions" src="https://img.shields.io/badge/6.x-versions?logo=wordpress&label=versions"></a>
    <a href="https://opensource.org/licenses/MIT"><img alt="Software License" src="https://img.shields.io/badge/Licence-MIT-brightgreen"></a>
</p>

------
**FastEndpoints** is an elegant way of writing custom WordPress REST endpoints with a focus on simplicity and readability.

- Explore our docs at **[FastEndpoints Docs »](https://attributes-php.github.io/wp-fastendpoints/)**

## Features

- Validates data via type-hints
- Removes unwanted fields from responses 
- Middlewares support
- No magic router. It uses WordPress [`register_rest_route`](https://developer.wordpress.org/reference/functions/register_rest_route/)
- Able to treat plugins as dependencies via [WP-FastEndpoints Depends](https://github.com/matapatos/wp-fastendpoints-depends)

## Requirements

- PHP 8.1+
- WordPress 6.x
- [attributes-php/validation](https://packagist.org/packages/Attributes-PHP/validation)
- [attributes-php/serialization](https://packagist.org/packages/Attributes-PHP/serialization)
- [php-di/invoker](https://packagist.org/packages/php-di/invoker)

We aim to support versions that haven't reached their end-of-life.

## Installation

```bash
composer require attributes-php/wp-fastendpoints
```

FastEndpoints was created by **[André Gil](https://www.linkedin.com/in/andre-gil/)** and is open-sourced software licensed under the **[MIT license](https://opensource.org/licenses/MIT)**.
