<?php

namespace Attributes\Wp\FastEndpoints\Tests\Models;

use Attributes\Options\Alias;
use Attributes\Options\AliasGenerator;
use Attributes\Options\Ignore;
use Attributes\Serialization\SerializableTrait;
use Attributes\Validation\Types\ArrayOf;
use Respect\Validation\Rules;

enum Status: string
{
    case PUBLISH = 'publish';
    case DRAFT = 'draft';
    case PRIVATE = 'private';
}

enum Type: string
{
    case PAGE = 'page';
    case POST = 'post';
}

#[AliasGenerator('snake')]
class Post
{
    use SerializableTrait;

    #[Ignore(serialization: false), Alias('ID')]
    #[Rules\Positive]
    public int $id;

    #[Rules\Positive]
    public int $postAuthor;

    public string $postTitle;

    public Status $postStatus;

    #[Ignore(serialization: false)]
    public Type $postType;
}

class PostsArr extends ArrayOf
{
    private Post $type;
}
