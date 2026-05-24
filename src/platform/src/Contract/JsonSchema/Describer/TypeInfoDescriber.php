<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

final class TypeInfoDescriber implements ObjectDescriberInterface, PropertyDescriberInterface, ObjectDescriberAwareInterface
{
    private ObjectDescriberInterface $objectDescriber;
    private TypeResolverInterface $typeResolver;

    public function __construct(?TypeResolverInterface $typeResolver = null)
    {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
    }

    public function setObjectDescriber(ObjectDescriberInterface $describer): void
    {
        $this->objectDescriber = $describer;
    }

    public function describeObject(ObjectSubject $subject, Schema $schema): iterable
    {
        if (null === $schema->anyOf && null === $schema->oneOf && null === $schema->allOf) {
            $schema->type ??= 'object';
        }

        return [];
    }

    public function describeProperty(PropertySubject $subject, Schema $schema): void
    {
        $reflector = $subject->getReflector();
        if (!$reflector->getDeclaringClass()->isUserDefined()) {
            return;
        }
        $type = $this->typeResolver->resolve($subject->getReflector());

        $subSchema = $this->getTypeSchema($type);
        if ($type->isNullable()) {
            if (null === $subSchema->anyOf) {
                $subSchema->type = (array) $subSchema->type;
                $subSchema->type[] = 'null';
            }
        }

        foreach ((array) $subSchema as $key => $value) {
            if (null !== $value) {
                $schema->{$key} = $value;
            }
        }
    }

    private function getTypeSchema(Type $type): Schema
    {
        // Handle BackedEnumType directly
        if ($type instanceof BackedEnumType) {
            return $this->buildEnumSchema($type->getClassName());
        }

        // Handle NullableType that wraps a BackedEnumType
        if ($type instanceof NullableType) {
            $wrappedType = $type->getWrappedType();
            if ($wrappedType instanceof BackedEnumType) {
                return $this->buildEnumSchema($wrappedType->getClassName());
            }
        }

        if ($type instanceof UnionType) {
            // Do not handle nullables as a union but directly return the wrapped type schema
            if (2 === \count($type->getTypes()) && $type->isNullable() && $type instanceof NullableType) {
                return $this->getTypeSchema($type->getWrappedType());
            }

            $variants = [];

            foreach ($type->getTypes() as $variant) {
                $variants[] = $this->getTypeSchema($variant);
            }

            return new Schema(anyOf: $variants);
        }

        switch (true) {
            case $type->isIdentifiedBy(TypeIdentifier::INT):
                return new Schema(type: 'integer');

            case $type->isIdentifiedBy(TypeIdentifier::FLOAT):
                return new Schema(type: 'number');

            case $type->isIdentifiedBy(TypeIdentifier::BOOL):
                return new Schema(type: 'boolean');

            case $type->isIdentifiedBy(TypeIdentifier::ARRAY):
                \assert($type instanceof CollectionType);

                $items = $this->getTypeSchema($type->getCollectionValueType());

                return new Schema(type: 'array', items: $items->isEmpty() ? null : $items);

            case $type->isIdentifiedBy(TypeIdentifier::OBJECT):
                if ($type instanceof BuiltinType) {
                    throw new InvalidArgumentException('Cannot build schema from plain object type.');
                }
                \assert($type instanceof ObjectType);

                $subSchema = new Schema();
                // Recursively build the schema for an object type
                $this->objectDescriber->describeObject(new ObjectSubject($type->getClassName(), new \ReflectionClass($type->getClassName())), $subSchema);

                if ($subSchema->isEmpty()) {
                    $subSchema->type = 'object';
                }

                return $subSchema;

            case $type->isIdentifiedBy(TypeIdentifier::NULL):
                return new Schema(type: 'null');

            case $type->isIdentifiedBy(TypeIdentifier::STRING):
                return new Schema(type: 'string');

            default:
                return new Schema();
        }
    }

    private function buildEnumSchema(string $enumClassName): Schema
    {
        $reflection = new \ReflectionEnum($enumClassName);

        if (!$reflection->isBacked()) {
            throw new InvalidArgumentException(\sprintf('Enum "%s" is not backed.', $enumClassName));
        }

        $cases = $reflection->getCases();
        $values = [];
        $backingType = $reflection->getBackingType();

        foreach ($cases as $case) {
            $values[] = $case->getBackingValue();
        }

        if (null === $backingType) {
            throw new InvalidArgumentException(\sprintf('Backed enum "%s" has no backing type.', $enumClassName));
        }

        $typeName = $backingType->getName();
        $jsonType = 'string' === $typeName ? 'string' : ('int' === $typeName ? 'integer' : 'string');

        return new Schema(type: $jsonType, enum: $values);
    }
}
