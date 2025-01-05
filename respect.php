<?php


$composer = __DIR__ . '/vendor/autoload.php';
require_once "$composer";

use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;
use Respect\Validation\Rules as Rules;

class CastException extends Exception {

}

/**
 * Yields each validation rule of a given property
 *
 * @param ReflectionProperty $property - Property to yield the rules from
 * @return Generator<Rules\AbstractRule>
 * @throws ReflectionException
 */
function getRulesFromProperty(ReflectionProperty $property): Generator
{
    $allAttributes = $property->getAttributes();
    if (!$allAttributes) {
        return;
    }

    foreach($allAttributes as $attribute) {
        if (! is_subclass_of($attribute->getName(), Rules\AbstractRule::class)) {
            continue;
        }

        // This could be changed in the future with $attribute->newInstance once each rule is marked as Attribute
        $reflectionClass = new ReflectionClass($attribute->getName());
        yield $reflectionClass->newInstanceArgs($attribute->getArguments());
    }
}

/**
 * @throws ReflectionException
 * @throws ValidationException if one of the rules fails
 */
function validatePropertyValue(ReflectionProperty $property, mixed $value): void
{
    foreach (getRulesFromProperty($property) as $rule) {
        $rule->assert($value);
    }
}

function castValueFromReflectionNamedType(ReflectionNamedType $propertyType, mixed $value): mixed
{
    if ($propertyType->isBuiltin()) {
        if (!settype($value, $propertyType->getName())) {
            throw new CastException("Invalid type '{$propertyType->getName()}'");
        }
    }

    return $value;
}

function castPropertyValue(ReflectionProperty $property, mixed $value): mixed
{
    if (!$property->hasType()) {
        return $value;
    }

    $propertyType = $property->getType();
    if ($propertyType instanceof ReflectionNamedType) {
        return castValueFromReflectionNamedType($propertyType, $value);
    }

    foreach ($propertyType->getTypes() as $type) {
        var_dump($type);
        try {
            return castValueFromReflectionNamedType($type, $value);
        } catch (CastException $error) {}
    }

    throw new CastException("Invalid type '{$propertyType->getName()}' for property '{$property->getName()}'");
}

class Person
{
    #[Rules\NumericVal]
    private float|int $age;
}

$personArray = [
    'age' => '10.90',
    'createdAt' => '2025-01-01a15:46:55',
];

$person = new Person();
$reflectionClass = new ReflectionClass($person);
$errors = [];
foreach($reflectionClass->getProperties() as $property) {
    $propertyName = $property->getName();

    if (!isset($personArray[$propertyName])) {
        if (!$property->isInitialized($person)) {
            $errors[$propertyName] = ["Missing required property '$propertyName'"];
        }
        continue;
    }

    $propertyValue = $personArray[$propertyName];
    try {
        validatePropertyValue($property, $propertyValue);
        $value = castPropertyValue($property, $propertyValue);
        $property->setValue($person, $value);
    } catch(ValidationException $error) {
        $errors[$propertyName] = [$error->getMessage()];
    } catch(CastException $error) {
        $errors[$propertyName] = [$error->getMessage()];
    }
}

if ($errors) {
    var_dump($errors);
    return;
}

var_dump($person);
