<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ValidatorConstraintsDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ValidatorConstraintsFixture;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ValidatorConstraintsIntlFixture;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Validator\Constraints\Xml;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

final class ValidatorConstraintsDescriberTest extends TestCase
{
    #[DataProvider('provideDescribeCases')]
    #[RequiresMethod(Yaml::class, 'parse')]
    public function testDescribe(string $property, ?Schema $initialSchema, Schema $expectedSchema)
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsFixture::class, $property);

        $schema = $initialSchema ?? new Schema();
        $describer->describeProperty(new PropertySubject($property, $propertyReflection), $schema);

        $this->assertEquals($expectedSchema, $schema);
    }

    #[RequiresMethod(Xml::class, '__construct')]
    #[RequiresPhpExtension('simplexml')]
    public function testDescribeXml()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsFixture::class, 'xml');

        $schema = new Schema();
        $describer->describeProperty(new PropertySubject('xml', $propertyReflection), $schema);

        $this->assertEquals(new Schema(contentMediaType: 'application/xml'), $schema);
    }

    /**
     * @return iterable<string, array{0: string, 1: Schema|null, 2: Schema}>
     */
    public static function provideDescribeCases(): iterable
    {
        yield 'AtLeastOneOf' => ['atLeastOneOf', null, new Schema(anyOf: [new Schema(const: 'a'), new Schema(type: 'integer')])];
        yield 'All' => ['all', null, new Schema(type: 'array', items: new Schema(maxLength: 255))];
        yield 'All, non-array type' => ['all', new Schema(type: 'string'), new Schema(type: 'string')];
        yield 'Blank string' => ['blankString', new Schema(type: 'string'), new Schema(type: 'string', nullable: true, maxLength: 0)];
        yield 'Cidr' => ['cidr', null, new Schema(description: 'Any IP address.')];
        yield 'Collection' => ['collection', null, new Schema(type: 'object', properties: ['a' => new Schema(const: 'hello'), 'b' => new Schema(const: 5)], required: ['a', 'b'])];
        yield 'Collection, non-object type ' => ['collection', new Schema(type: 'array'), new Schema(type: 'array')];
        yield 'Count and unique array' => ['countedArray', null, new Schema(minItems: 2, maxItems: 4, uniqueItems: true)];
        yield 'CssColor' => ['cssColor', new Schema(description: 'Background color.'), new Schema(description: "Background color.\nCSS color in one of the following formats: hex_short, hex_long")];
        yield 'Choice string' => ['choiceString', null, new Schema(enum: ['a', 'b'])];
        yield 'Choice array' => ['choiceArray', new Schema(type: 'array', items: new Schema(type: 'string')), new Schema(type: 'array', items: new Schema(type: 'string', enum: ['x', 'y']), minItems: 1, maxItems: 2)];
        yield 'Choice callback' => ['choiceCallback', new Schema(type: 'integer'), new Schema(type: 'integer', enum: [1, 2, 3])];
        yield 'Choice match=false' => ['choiceInverse', new Schema(not: new Schema(enum: [1])), new Schema(not: new Schema(enum: [1, 2, 3]))];
        yield 'Date format' => ['date', null, new Schema(format: 'date')];
        yield 'DateTime format' => ['dateTime', null, new Schema(format: 'date-time')];
        yield 'Email format' => ['email', null, new Schema(format: 'email')];
        yield 'EqualTo' => ['equalTo', null, new Schema(const: 'foo')];
        yield 'Expression' => ['expression', null, new Schema(description: 'Must match Symfony Expression Language rule: "this.expression != null"')];
        yield 'ExpressionSyntax' => ['expressionSyntax', null, new Schema(description: 'Syntax: Symfony Expression Language. Available variables: foo, bar')];
        yield 'Hostname format' => ['hostname', null, new Schema(format: 'hostname')];
        yield 'IBAN' => ['iban', null, new Schema(description: 'IBAN without spaces or other separator characters.')];
        yield 'IPv4 format' => ['ipv4', null, new Schema(format: 'ipv4')];
        yield 'IPv6 format' => ['ipv6', null, new Schema(format: 'ipv6')];
        yield 'IsFalse const' => ['isFalse', null, new Schema(const: false)];
        yield 'IsNull const' => ['isNull', new Schema(type: ['string', 'null']), new Schema(type: 'null')];
        yield 'IsTrue const' => ['isTrue', null, new Schema(const: true)];
        yield 'Json' => ['json', null, new Schema(contentMediaType: 'application/json')];
        yield 'Length string' => ['lengthString', null, new Schema(minLength: 2, maxLength: 4)];
        yield 'MacAddress' => ['macAddress', null, new Schema(description: 'MAC address, accepted type: all.')];
        yield 'Negative or zero' => ['negativeNumber', null, new Schema(maximum: 0)];
        yield 'NotBlank string' => ['notBlankString', new Schema(type: 'string'), new Schema(type: 'string', nullable: false, minLength: 1)];
        yield 'NotNull nullable false' => ['notNull', null, new Schema(nullable: false)];
        yield 'NotNull with null type' => ['notNull', new Schema(type: ['string', 'null']), new Schema(type: 'string', nullable: false)];
        yield 'NotEqualTo' => ['notEqualTo', null, new Schema(not: new Schema(enum: ['bar']))];
        yield 'Numeric range' => ['numberRange', null, new Schema(minimum: 10, maximum: 100, multipleOf: 3, exclusiveMinimum: true)];
        yield 'Positive' => ['positiveNumber', null, new Schema(minimum: 0, exclusiveMinimum: true)];
        yield 'Range constraint' => ['rangedNumber', null, new Schema(minimum: 5, maximum: 15)];
        yield 'Regex string' => ['regexString', null, new Schema(pattern: '[a-z]+')];
        yield 'Time pattern' => ['time', null, new Schema(pattern: '^([01]\d|2[0-3]):[0-5]\d$')];
        yield 'Timezone' => ['timezone', null, new Schema(description: 'Timezone in "Region/City" format.')];
        yield 'Type constraint narrows schema type' => ['typedByConstraint', new Schema(type: ['string', 'null', 'integer']), new Schema(type: ['string', 'null'])];
        yield 'Ulid pattern' => ['ulid', null, new Schema(pattern: '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')];
        yield 'Url format' => ['url', null, new Schema(format: 'uri')];
        yield 'Uuid format' => ['uuid', null, new Schema(format: 'uuid')];
        yield 'Week' => ['week', null, new Schema(pattern: '^[0-9]{4}W[0-9]{2}$')];
        yield 'WordCount between' => ['wordCountBetween', null, new Schema(description: 'Word count must be between 10 and 20.')];
        yield 'WordCount minimum' => ['wordCountMinimum', null, new Schema(description: 'Word count must be at least 10.')];
        yield 'WordCount maximum' => ['wordCountMaximum', null, new Schema(description: 'Word count must be no more than 20.')];
        yield 'Yaml' => ['yaml', null, new Schema(contentMediaType: 'application/yaml')];
    }

    #[DataProvider('describeIntlProvider')]
    #[RequiresMethod(Countries::class, 'exists')]
    public function testDescribeIntl(string $property, ?Schema $initialSchema, Schema $expectedSchema)
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsIntlFixture::class, $property);

        $schema = $initialSchema ?? new Schema();
        $describer->describeProperty(new PropertySubject($property, $propertyReflection), $schema);

        $this->assertEquals($expectedSchema, $schema);
    }

    public static function describeIntlProvider(): iterable
    {
        yield 'Country alpha-2' => ['countryAlpha2', null, new Schema(description: 'ISO 3166-1 alpha-2 country code', pattern: '^[A-Z]{2}$')];
        yield 'Country alpha-3' => ['countryAlpha3', null, new Schema(description: 'ISO 3166-1 alpha-3 country code', pattern: '^[A-Z]{3}$')];
        yield 'Language alpha-2' => ['languageAlpha2', null, new Schema(description: 'ISO 639-1 language code', pattern: '^[a-z]{2}$')];
        yield 'Language alpha-3' => ['languageAlpha3', null, new Schema(description: 'ISO 639-2 (2T) language code', pattern: '^[a-z]{3}$')];
        yield 'Currency' => ['currency', null, new Schema(description: 'ISO 4217 currency code', pattern: '^[A-Z]{3}$')];
        yield 'Locale' => ['locale', null, new Schema(pattern: '^[a-z]{2}([_-][A-Z]{2})?$')];
    }
}
