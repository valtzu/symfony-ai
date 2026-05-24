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
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidatorConstraintsDescriber implements PropertyDescriberInterface
{
    private readonly ValidatorInterface $validator;

    public function __construct(
        ?ValidatorInterface $validator = null,
    ) {
        $this->validator = $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function describeProperty(PropertySubject $subject, Schema $schema): void
    {
        $reflector = $subject->getReflector();
        if ($reflector instanceof \ReflectionParameter) {
            return;
        }

        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->validator->getMetadataFor($reflector->class);
        $propertyMetadata = $classMetadata->getPropertyMetadata($subject->getName());

        foreach ($propertyMetadata as $metadata) {
            foreach ($metadata->getConstraints() as $constraint) {
                $this->applyConstraints($constraint, $schema, $reflector->class);
            }
        }
    }

    private function applyConstraints(Constraint $constraint, Schema $schema, string $class): void
    {
        match (true) {
            $constraint instanceof Assert\All => $this->describeAll($schema, $constraint, $class),
            $constraint instanceof Assert\AtLeastOneOf => $this->describeAtLeastOneOf($schema, $constraint, $class),
            $constraint instanceof Assert\Bic => $this->appendDescription('Business Identifier Code (BIC).', $schema),
            $constraint instanceof Assert\Blank => $this->describeBlank($schema),
            $constraint instanceof Assert\Choice => $this->describeChoice($schema, $constraint, $class),
            $constraint instanceof Assert\Cidr => $this->describeCidr($schema, $constraint),
            $constraint instanceof Assert\Collection => $this->describeCollection($schema, $constraint, $class),
            $constraint instanceof Assert\Compound => $this->describeCompound($schema, $constraint, $class),
            $constraint instanceof Assert\Count => $this->describeCount($schema, $constraint),
            $constraint instanceof Assert\Country => $this->describeCountry($schema, $constraint),
            $constraint instanceof Assert\CssColor => $this->appendDescription('CSS color in one of the following formats: '.implode(', ', $constraint->formats), $schema),
            $constraint instanceof Assert\Currency => $this->describeCurrency($schema),
            $constraint instanceof Assert\Date => $schema->format = 'date',
            $constraint instanceof Assert\DateTime => $schema->format = 'date-time',
            $constraint instanceof Assert\DivisibleBy => $this->describeDivisibleBy($schema, $constraint),
            $constraint instanceof Assert\Email => $schema->format = 'email',
            $constraint instanceof Assert\EqualTo, $constraint instanceof Assert\IdenticalTo => $this->describeEqualTo($schema, $constraint),
            $constraint instanceof Assert\Expression => $this->appendDescription(\sprintf('Must match Symfony Expression Language rule: "%s"', $constraint->expression), $schema),
            $constraint instanceof Assert\ExpressionSyntax => $this->appendDescription('Syntax: Symfony Expression Language.'.($constraint->allowedVariables ? ' Available variables: '.implode(', ', $constraint->allowedVariables) : ''), $schema),
            $constraint instanceof Assert\GreaterThanOrEqual => $this->describeLowerBound($schema, $constraint, false),
            $constraint instanceof Assert\GreaterThan => $this->describeLowerBound($schema, $constraint, true),
            $constraint instanceof Assert\Hostname => $schema->format = 'hostname',
            $constraint instanceof Assert\Iban => $this->appendDescription('IBAN without spaces or other separator characters.', $schema),
            $constraint instanceof Assert\Ip => $this->describeIp($schema, $constraint),
            $constraint instanceof Assert\IsFalse => $schema->const = false,
            $constraint instanceof Assert\IsNull => $this->describeIsNull($schema),
            $constraint instanceof Assert\IsTrue => $schema->const = true,
            $constraint instanceof Assert\Json => $schema->contentMediaType = 'application/json',
            $constraint instanceof Assert\Language => $this->describeLanguage($schema, $constraint),
            $constraint instanceof Assert\Length => $this->describeLength($schema, $constraint),
            $constraint instanceof Assert\LessThanOrEqual => $this->describeUpperBound($schema, $constraint, false),
            $constraint instanceof Assert\LessThan => $this->describeUpperBound($schema, $constraint, true),
            $constraint instanceof Assert\Locale => $schema->pattern = '^[a-z]{2}([_-][A-Z]{2})?$',
            $constraint instanceof Assert\MacAddress => $this->appendDescription('MAC address, accepted type: '.$constraint->type.'.', $schema),
            $constraint instanceof Assert\NotBlank => $this->describeNotBlank($schema, $constraint),
            $constraint instanceof Assert\NotNull => $this->describeNotNull($schema),
            $constraint instanceof Assert\NotEqualTo, $constraint instanceof Assert\NotIdenticalTo => $this->describeNotEqualTo($schema, $constraint),
            $constraint instanceof Assert\Range => $this->describeRange($schema, $constraint),
            $constraint instanceof Assert\Regex => $this->describeRegex($schema, $constraint),
            $constraint instanceof Assert\Time => $this->describeTime($schema, $constraint),
            $constraint instanceof Assert\Timezone => $this->appendDescription('Timezone in "Region/City" format.', $schema),
            $constraint instanceof Assert\Type => $this->describeType($schema, $constraint),
            $constraint instanceof Assert\Ulid => $this->describeUlid($schema, $constraint),
            $constraint instanceof Assert\Unique => $schema->uniqueItems = true,
            $constraint instanceof Assert\Url => $schema->format = 'uri',
            $constraint instanceof Assert\Uuid => $schema->format = 'uuid',
            $constraint instanceof Assert\Week => $schema->pattern = '^[0-9]{4}W[0-9]{2}$',
            $constraint instanceof Assert\WordCount => $this->describeWordCount($schema, $constraint),
            $constraint instanceof Assert\Xml => $schema->contentMediaType = 'application/xml',
            $constraint instanceof Assert\Yaml => $schema->contentMediaType = 'application/yaml',
            default => null,
        };
    }

    private function describeRegex(Schema $schema, Assert\Regex $constraint): void
    {
        $schema->pattern = $constraint->getHtmlPattern();
    }

    private function describeNotBlank(Schema $schema, Assert\NotBlank $constraint): void
    {
        $schema->nullable = $constraint->allowNull;

        if ($this->containsType($schema, 'string')) {
            $schema->minLength = 1;
        }

        if ($this->containsType($schema, 'object')) {
            $schema->minProperties = 1;
        }

        if ($this->containsType($schema, 'array')) {
            $schema->minItems = 1;
        }
    }

    private function describeBlank(Schema $schema): void
    {
        $schema->nullable = true;

        if ($this->containsType($schema, 'string')) {
            $schema->maxLength = 0;
        }
    }

    private function describeNotEqualTo(Schema $schema, Assert\NotEqualTo|Assert\NotIdenticalTo $constraint): void
    {
        if ($constraint->propertyPath) {
            return;
        }

        $schema->not ??= new Schema();
        $schema->not->enum ??= [];
        $schema->not->enum[] = $constraint->value;
    }

    private function describeEqualTo(Schema $schema, Assert\EqualTo|Assert\IdenticalTo $constraint): void
    {
        if ($constraint->propertyPath) {
            return;
        }

        $schema->const = $constraint->value;
    }

    private function describeChoice(Schema $schema, Assert\Choice $constraint, string $class): void
    {
        if ($constraint->callback) {
            if (\is_callable($choices = [$class, $constraint->callback]) || \is_callable($choices = $constraint->callback)) {
                $choices = $choices();
            } else {
                return;
            }
        } else {
            $choices = $constraint->choices;
        }

        if (!\is_array($choices)) {
            return;
        }

        if ($constraint->multiple) {
            $schema->items ??= new Schema();
            $schema->items->enum = $choices;
            if (null !== $constraint->min) {
                $schema->minItems = $constraint->min;
            }
            if (null !== $constraint->max) {
                $schema->maxItems = $constraint->max;
            }
        } else {
            if ($constraint->match) {
                $schema->enum = $choices;
            } else {
                $schema->not ??= new Schema();
                $schema->not->enum ??= [];
                foreach ($choices as $choice) {
                    if (\in_array($choice, $schema->not->enum, true)) {
                        continue;
                    }

                    $schema->not->enum[] = $choice;
                }
            }
        }
    }

    private function describeLowerBound(Schema $schema, Assert\AbstractComparison $constraint, bool $exclusive): void
    {
        if (null !== $constraint->propertyPath || !\is_scalar($constraint->value)) {
            return;
        }

        if (!is_numeric($constraint->value)) {
            $this->appendDescription('Minimum value: '.$constraint->value, $schema);

            return;
        }

        $schema->minimum = $constraint->value;
        if ($exclusive) {
            $schema->exclusiveMinimum = true;
        }
    }

    private function describeUpperBound(Schema $schema, Assert\AbstractComparison $constraint, bool $exclusive): void
    {
        if (null !== $constraint->propertyPath || !\is_scalar($constraint->value)) {
            return;
        }

        if (!is_numeric($constraint->value)) {
            $this->appendDescription('Maximum value: '.$constraint->value, $schema);

            return;
        }

        $schema->maximum = $constraint->value;
        if ($exclusive) {
            $schema->exclusiveMaximum = true;
        }
    }

    private function describeRange(Schema $schema, Assert\Range $constraint): void
    {
        if (null === $constraint->minPropertyPath && \is_scalar($constraint->min)) {
            if (is_numeric($constraint->min)) {
                $schema->minimum = $constraint->min;
            } else {
                $this->appendDescription('Minimum value: '.$constraint->min, $schema);
            }
        }

        if (null === $constraint->maxPropertyPath && \is_scalar($constraint->max)) {
            if (is_numeric($constraint->max)) {
                $schema->maximum = $constraint->max;
            } else {
                $this->appendDescription('Maximum value: '.$constraint->max, $schema);
            }
        }
    }

    private function describeDivisibleBy(Schema $schema, Assert\DivisibleBy $constraint): void
    {
        if (null !== $constraint->propertyPath || !is_numeric($constraint->value)) {
            return;
        }

        $schema->multipleOf = $constraint->value;
    }

    private function describeCount(Schema $schema, Assert\Count $constraint): void
    {
        if (null !== $constraint->min) {
            $schema->minItems = $constraint->min;
        }

        if (null !== $constraint->max) {
            $schema->maxItems = $constraint->max;
        }
    }

    private function describeLength(Schema $schema, Assert\Length $constraint): void
    {
        if (null !== $constraint->min) {
            $schema->minLength = $constraint->min;
        }

        if (null !== $constraint->max) {
            $schema->maxLength = $constraint->max;
        }
    }

    private function describeTime(Schema $schema, Assert\Time $constraint): void
    {
        if ($constraint->withSeconds) {
            $schema->format = 'time';

            return;
        }

        $schema->pattern = '^([01]\d|2[0-3]):[0-5]\d$';
    }

    private function describeUlid(Schema $schema, Assert\Ulid $constraint): void
    {
        match ($constraint->format) {
            Assert\Ulid::FORMAT_BASE_32 => $schema->pattern = '^[0-7][0-9A-HJKMNP-TV-Z]{25}$',
            Assert\Ulid::FORMAT_BASE_58 => $schema->pattern = '^[1-9A-HJ-NP-Za-km-z]{22}$',
            Assert\Ulid::FORMAT_RFC_4122 => $schema->format = 'uuid',
            default => null,
        };
    }

    private function describeIp(Schema $schema, Assert\Ip $constraint): void
    {
        if (str_starts_with($constraint->version, Assert\Ip::V4)) {
            $schema->format = 'ipv4';

            return;
        }

        if (str_starts_with($constraint->version, Assert\Ip::V6)) {
            $schema->format = 'ipv6';
        }
    }

    private function describeIsNull(Schema $schema): void
    {
        $schema->type = 'null';
    }

    private function describeType(Schema $schema, Assert\Type $constraint): void
    {
        $constraintTypes = \is_array($constraint->type) ? $constraint->type : [$constraint->type];
        $jsonSchemaTypes = [];

        foreach ($constraintTypes as $constraintType) {
            $jsonSchemaType = $this->mapConstraintTypeToJsonSchemaType($constraintType);

            if (null !== $jsonSchemaType) {
                $jsonSchemaTypes[] = $jsonSchemaType;
            }
        }

        $jsonSchemaTypes = array_values(array_unique($jsonSchemaTypes));
        if ([] === $jsonSchemaTypes) {
            return;
        }

        if (null === $schema->type) {
            $schema->type = 1 === \count($jsonSchemaTypes) ? $jsonSchemaTypes[0] : $jsonSchemaTypes;

            return;
        }

        $existingTypes = \is_array($schema->type) ? $schema->type : [$schema->type];
        $intersectedTypes = array_values(array_intersect($existingTypes, $jsonSchemaTypes));

        if ([] !== $intersectedTypes) {
            $schema->type = 1 === \count($intersectedTypes) ? $intersectedTypes[0] : $intersectedTypes;
        }
    }

    private function containsType(Schema $schema, string $type): bool
    {
        if (null === $schema->type) {
            return false;
        }

        $types = \is_array($schema->type) ? $schema->type : [$schema->type];

        return \in_array($type, $types, true);
    }

    private function mapConstraintTypeToJsonSchemaType(string $constraintType): ?string
    {
        return match ($constraintType) {
            'int', 'integer' => 'integer',
            'float', 'double', 'real', 'number', 'numeric' => 'number',
            'bool', 'boolean' => 'boolean',
            'array', 'list' => 'array',
            'object' => 'object',
            'string' => 'string',
            'null' => 'null',
            default => null,
        };
    }

    private function appendDescription(string $description, Schema $schema): void
    {
        $schema->description ??= '';
        if ($schema->description) {
            $schema->description .= "\n";
        }

        $schema->description .= $description;
    }

    private function describeAtLeastOneOf(Schema $schema, Assert\AtLeastOneOf $constraint, string $class): void
    {
        foreach ((array) $constraint->constraints as $constraint) {
            $anyOf = new Schema();
            $this->applyConstraints($constraint, $anyOf, $class);
            if (!$anyOf->isEmpty()) {
                $schema->anyOf ??= [];
                $schema->anyOf[] = $anyOf;
            }
        }
    }

    private function describeAll(Schema $schema, Assert\All $constraint, string $class): void
    {
        // Since additionalProperties is not supported by all (or none?) platforms, we only support non-assoc arrays here.
        $schema->type ??= 'array';
        if (!\in_array('array', (array) $schema->type, true)) {
            return;
        }

        $schema->items ??= new Schema();
        foreach ((array) $constraint->constraints as $constraint) {
            $this->applyConstraints($constraint, $schema->items, $class);
        }
    }

    private function describeCollection(Schema $schema, Assert\Collection $constraint, string $class): void
    {
        $schema->type ??= 'object';
        if (!\in_array('object', (array) $schema->type, true)) {
            return;
        }

        foreach ($constraint->fields as $field => $fieldConstraints) {
            if (!\is_array($fieldConstraints)) {
                $fieldConstraints = [$fieldConstraints];
            }
            $schema->properties ??= [];
            $schema->properties[$field] ??= new Schema();
            foreach ($fieldConstraints as $fieldConstraint) {
                if (!$fieldConstraint instanceof Assert\Existence) {
                    $this->applyConstraints($fieldConstraint, $schema->properties[$field], $class);
                    continue;
                }

                $nestedConstraints = !\is_array($fieldConstraint->constraints) ? [$fieldConstraint->constraints] : $fieldConstraint->constraints;
                foreach ($nestedConstraints as $nestedConstraint) {
                    $this->applyConstraints($nestedConstraint, $schema->properties[$field], $class);
                }
            }
        }

        if (!$constraint->allowMissingFields) {
            $schema->required = array_values(array_unique(array_merge($schema->required ?? [], array_keys($constraint->fields))));
        }
    }

    private function describeCompound(Schema $schema, Assert\Compound $constraint, string $class): void
    {
        foreach ($constraint->constraints as $constraint) {
            $this->applyConstraints($constraint, $schema, $class);
        }
    }

    private function describeCountry(Schema $schema, Assert\Country $constraint): void
    {
        $schema->pattern = $constraint->alpha3 ? '^[A-Z]{3}$' : '^[A-Z]{2}$';
        $this->appendDescription(\sprintf('ISO 3166-1 alpha-%d country code', $constraint->alpha3 ? 3 : 2), $schema);
    }

    private function describeLanguage(Schema $schema, Assert\Language $constraint): void
    {
        $schema->pattern = $constraint->alpha3 ? '^[a-z]{3}$' : '^[a-z]{2}$';
        $this->appendDescription($constraint->alpha3 ? 'ISO 639-2 (2T) language code' : 'ISO 639-1 language code', $schema);
    }

    private function describeWordCount(Schema $schema, Assert\WordCount $constraint): void
    {
        $description = match ([null !== $constraint->min, null !== $constraint->max]) {
            [true, true] => \sprintf('Word count must be between %d and %d.', $constraint->min, $constraint->max),
            [true, false] => \sprintf('Word count must be at least %d.', $constraint->min),
            [false, true] => \sprintf('Word count must be no more than %d.', $constraint->max),
            default => null,
        };

        if (!$description) {
            return;
        }

        $this->appendDescription($description, $schema);
    }

    private function describeCidr(Schema $schema, Assert\Cidr $constraint): void
    {
        $ipVersion = match ($constraint->version) {
            Assert\Ip::V4 => 'IPv4',
            Assert\Ip::V6 => 'IPv6',
            Assert\Ip::ALL => 'Any IP',

            Assert\Ip::V4_NO_PUBLIC => 'IPv4 (excl. public)',
            Assert\Ip::V6_NO_PUBLIC => 'IPv6 (excl. public)',
            Assert\Ip::ALL_NO_PUBLIC => 'Any IP (excl. public)',

            Assert\Ip::V4_NO_PRIVATE => 'IPv4 (excl. private)',
            Assert\Ip::V6_NO_PRIVATE => 'IPv6 (excl. private)',
            Assert\Ip::ALL_NO_PRIVATE => 'Any IP (excl. private)',

            Assert\Ip::V4_NO_RESERVED => 'IPv4 (excl. reserved)',
            Assert\Ip::V6_NO_RESERVED => 'IPv6 (excl. reserved)',
            Assert\Ip::ALL_NO_RESERVED => 'Any IP (excl. reserved)',

            Assert\Ip::V4_ONLY_PUBLIC => 'Public IPv4',
            Assert\Ip::V6_ONLY_PUBLIC => 'Public IPv6',
            Assert\Ip::ALL_ONLY_PUBLIC => 'Any public IP',

            Assert\Ip::V4_ONLY_PRIVATE => 'Private IPv4',
            Assert\Ip::V6_ONLY_PRIVATE => 'Private IPv6',
            Assert\Ip::ALL_ONLY_PRIVATE => 'Any private IP',

            Assert\Ip::V4_ONLY_RESERVED => 'Reserved IPv4',
            Assert\Ip::V6_ONLY_RESERVED => 'Reserved IPv6',
            Assert\Ip::ALL_ONLY_RESERVED => 'Any reserved IP',

            default => null,
        };

        if (!$ipVersion) {
            return;
        }

        $this->appendDescription($ipVersion.' address.', $schema);
    }

    private function describeNotNull(Schema $schema): void
    {
        $schema->nullable = false;

        if (\in_array($schema->type, ['null', ['null']], true)) {
            $schema->type = null;
        } elseif (\in_array('null', (array) ($schema->type ?? []), true)) {
            $schema->type = array_values(array_filter((array) $schema->type, static fn ($item) => 'null' !== $item));

            if (1 === \count($schema->type)) {
                [$schema->type] = $schema->type;
            }
        }
    }

    private function describeCurrency(Schema $schema): void
    {
        $schema->pattern = '^[A-Z]{3}$';
        $this->appendDescription('ISO 4217 currency code', $schema);
    }
}
