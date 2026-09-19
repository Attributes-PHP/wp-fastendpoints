---
title: "Testing"
---

To test our router endpoints we are going to use [Pest PHP](https://pestphp.com/). Pest is a testing framework which makes PHP testing
a breeze. Besides, the creator of Pest is Portuguese and studied at the same university as me which is a bonus 🌟😂

However, if you have a preference for some other testing framework, the same principles apply

## Testing dependencies

To allow us mocking classes and function we will be using [Mockery](https://github.com/mockery/mockery)

```bash
composer require mockery/mockery --dev
```

## Testing structure

We are going to write unit and integration tests. Our code structure would look like the following:

```text
my-plugin
│   (...)
│
└───src
│       (...)
│
└───tests
    │   bootstrap.php   # Loads WordPress for integration tests
    │   Helpers.php     # (optional) Helper functions
    │   Pest.php        # Pest configuration file
    │
    └───Integration
    │   │    PostsApiTest.php
    │
    └───Unit
        │    PostsApiTest.php
```
