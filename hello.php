<?php

$composer = __DIR__.'/vendor/autoload.php';
require_once "$composer";

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Validation;

/**
 * Converts a dictionary of violations into a dictionary with error messages.
 *
 * @param array<string,ConstraintViolationList> $errors
 * @return array<string,array<string>>
 */
function getErrorMessages(array $errors): array {
    $errorMessages = [];
    foreach ($errors as $propertyName => $violations) {
        foreach($violations as $violation) {
            if (!isset($errorMessages[$propertyName])) {
                $errorMessages[$propertyName] = [];
            }

            $errorMessages[$propertyName][] = $violation->getMessage();
        }
    }

    return $errorMessages;
}

function getClassErrorMessages(ConstraintViolationList $violations): array {
    $errorMessages = [];
    foreach($violations as $violation) {
        $propertyPath = $violation->getPropertyPath();
        if (!isset($errorMessages[$propertyPath])) {
            $errorMessages[$propertyPath] = [];
        }

        $errorMessages[$propertyPath][] = $violation->getMessage();
    }

    return $errorMessages;
}

$classMetadataFactory = new ClassMetadataFactory(new AttributeLoader);
$reflectionExtractor = new ReflectionExtractor();
$normalizers = [
    new UidNormalizer(), new DateTimeNormalizer(),
    new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new BackedEnumNormalizer(),
    new DataUriNormalizer(), new ArrayDenormalizer(),
    new ObjectNormalizer(classMetadataFactory: $classMetadataFactory, propertyTypeExtractor: $reflectionExtractor),
    new GetSetMethodNormalizer(classMetadataFactory: $classMetadataFactory, propertyTypeExtractor: $reflectionExtractor),];

$serializer = new Serializer($normalizers);

class Person
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    #[Assert\Positive]
    public int $age;

//    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[Assert\DateTime]
    public DateTime $createdAt;
}

$personArray = [
    'name' => 'John Doe',
    'age' => '10',
    'createdAt' => '2025-01-01 15:46:55',
];

$validator = Validation::createValidatorBuilder()
    ->enableAttributeMapping()
    ->getValidator();

$reflectionClass = new ReflectionClass(Person::class);
$errors = [];
foreach ($personArray as $name => $value) {
    if (!$reflectionClass->hasProperty($name)) {
        unset($personArray[$name]);
        continue;
    }

    $violations = $validator->validatePropertyValue(Person::class, $name, $value);
    if ($violations->count() > 0) {
        $errors[$name] = $violations;
        continue;
    }

    $property = $reflectionClass->getProperty($name);
    if (!$property->hasType()) {
        continue;
    }

    $propertyType = $property->getType();
    if (!$propertyType->isBuiltin()) {
        continue;
    }

    settype($value, $propertyType->getName());
    $personArray[$name] = $value;
}

if ($errors) {
    $errorMessages = getErrorMessages($errors);
    echo "-------- Properties validation -----------\n";
    var_dump($errorMessages);
    echo "------------------------------------------\n";
    return;
}

$person = $serializer->denormalize($personArray, Person::class);

$violations = $validator->validate($person);
if ($violations->count() > 0) {
    $errorMessages = getClassErrorMessages($violations);
    echo "---------- Class validation --------------\n";
    var_dump($errorMessages);
    echo "------------------------------------------\n";
    return;
}

var_dump($person);
