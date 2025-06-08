---
hide:
  - navigation
---

<img src="https://raw.githubusercontent.com/Attributes-PHP/wp-fastendpoints/main/docs/images/wp-fastendpoints-wallpaper.png" alt="WordPress REST endpoints made easy">
<p align="center">
    <a href="https://github.com/Attributes-PHP/wp-fastendpoints/actions"><img alt="GitHub Actions Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/Attributes-PHP/wp-fastendpoints/tests.yml"></a>
    <a href="https://codecov.io/gh/Attributes-PHP/wp-fastendpoints" ><img alt="Code Coverage" src="https://codecov.io/gh/Attributes-PHP/wp-fastendpoints/graph/badge.svg?token=8N7N9NMGLG"/></a>
    <a href="https://packagist.org/packages/Attributes-PHP/wp-fastendpoints"><img alt="Latest Version" src="https://img.shields.io/packagist/v/Attributes-PHP/wp-fastendpoints"></a>
    <a href="https://packagist.org/packages/Attributes-PHP/wp-fastendpoints"><img alt="Supported WordPress Versions" src="https://img.shields.io/badge/6.x-versions?logo=wordpress&label=versions"></a>
    <a href="https://packagist.org/packages/Attributes-PHP/wp-fastendpoints"><img alt="Software License" src="https://img.shields.io/packagist/l/Attributes-PHP/wp-fastendpoints"></a>
</p>

------
**FastEndpoints** is an elegant way of writing custom WordPress REST endpoints with a focus on readability and IDE auto completion support.

## Features

- Decouples request validation from main logic
- Removes unwanted fields from responses
- Middlewares support
- IDE auto completion support
- No magic router. It uses WordPress [`register_rest_route`](https://developer.wordpress.org/reference/functions/register_rest_route/)
- Support for newer JSON schema drafts thanks to [opis/json-schema](https://opis.io/json-schema/2.x/)
- Able to treat plugins as dependencies via [WP-FastEndpoints Depends](https://github.com/Attributes-PHP/wp-fastendpoints-depends)

## Requirements

- PHP 8.1+
- WordPress 6.x
- [attributes-php/validation](https://github.com/Attributes-PHP/validation)
- [php-di/invoker](https://packagist.org/packages/php-di/invoker)

We aim to support versions that haven't reached their end-of-life.

## Installation

```bash
composer require attributes-php/wp-fastendpoints
```

## Sponsors

{% if sponsors %}
{% for sponsor in sponsors.companies -%}
<a href="{{ sponsor.url }}" target="_blank" title="{{ sponsor.title }}"><img src="{{ sponsor.img }}" style="border-radius:15px; max-width: 200px; max-height: 200px;"></a>
{% endfor -%}
{% endif %}

<!-- /sponsors -->
