<?php
/*
 * This file is part of Swagger Mock.
 *
 * (c) Igor Lazarev <strider2038@yandex.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\OpenAPI\Parsing;

use App\OpenAPI\Parsing\ParsingException;
use App\OpenAPI\Parsing\SpecificationAccessor;
use App\OpenAPI\Parsing\SpecificationObjectParser;
use App\OpenAPI\Parsing\SpecificationPointer;
use App\OpenAPI\SpecificationObjectMarkerInterface;
use App\Tests\Utility\TestCase\ContextualParserTestCaseTrait;
use PHPUnit\Framework\TestCase;

class SpecificationObjectParserTest extends TestCase
{
    use ContextualParserTestCaseTrait;
    private const REFERENCE = '#/reference';

    /** @var SpecificationAccessor */
    private $specificationAccessor;

    protected function setUp(): void
    {
        $this->specificationAccessor = \Phake::mock(SpecificationAccessor::class);
        $this->setUpContextualParser();
    }

    /** @test */
    public function parseObject_schemaIsNotReference_contextualParserParsesSchemaAndReturnsObject(): void
    {
        $objectParser = new SpecificationObjectParser();
        $pointer = new SpecificationPointer();
        $this->givenSpecificationAccessor_getSchema_returnsSchema(['schema']);
        $expectedObject = $this->givenContextualParser_parsePointedSchema_returnsObject();

        $object = $objectParser->parseObject($this->specificationAccessor, $pointer, $this->contextualParser);

        $this->assertSpecificationAccessor_getSchema_wasCalledOnceWithPointer($pointer);
        $this->assertContextualParser_parsePointedSchema_wasCalledOnceWithSpecificationAndPointer($this->specificationAccessor, $pointer);
        $this->assertSame($expectedObject, $object);
    }

    /**
     * @test
     * @dataProvider referenceAndReferencedPointerPath
     */
    public function parseObject_schemaWithNotResolvedReference_contextualParserParsesReferencedSchemaAndReturnsObject(
        string $reference,
        array $referencedPointerPath
    ): void {
        $objectParser = new SpecificationObjectParser();
        $pointer = new SpecificationPointer();
        $this->givenSpecificationAccessor_getSchema_returnsSchema(['$ref' => $reference]);
        $expectedObject = $this->givenContextualParser_parsePointedSchema_returnsObject();

        $object = $objectParser->parseObject($this->specificationAccessor, $pointer, $this->contextualParser);

        $this->assertSpecificationAccessor_getSchema_wasCalledOnceWithPointer($pointer);
        $this->assertContextualParser_parsePointedSchema_wasCalledOnceWithSpecificationAndPointerPath($this->specificationAccessor, $referencedPointerPath);
        $this->assertSame($expectedObject, $object);
    }

    public function referenceAndReferencedPointerPath(): array
    {
        return [
            [self::REFERENCE, ['reference']],
            ['#/one/two/three', ['one', 'two', 'three']],
            ['#/paths/~1blogs~1{blog_id}~1new~0posts', ['paths', '/blogs/{blog_id}/new~posts']],
        ];
    }

    /**
     * @test
     * @dataProvider invalidReferenceAndExceptionMessageProvider
     */
    public function parseObject_schemaWithInvalidReference_exceptionThrown(string $reference, string $exceptionMessage): void
    {
        $objectParser = new SpecificationObjectParser();
        $pointer = new SpecificationPointer();
        $this->givenSpecificationAccessor_getSchema_returnsSchema(['$ref' => $reference]);

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $objectParser->parseObject($this->specificationAccessor, $pointer, $this->contextualParser);
    }

    /** @test */
    public function parseObject_schemaWithNotResolvedReference_parsedObjectSetToSpecification(): void
    {
        $objectParser = new SpecificationObjectParser();
        $pointer = new SpecificationPointer();
        $this->givenSpecificationAccessor_getSchema_returnsSchema(['$ref' => self::REFERENCE]);
        $expectedObject = $this->givenContextualParser_parsePointedSchema_returnsObject();
        $this->givenSpecificationAccessor_findResolvedObject_returnsNull();

        $object = $objectParser->parseObject($this->specificationAccessor, $pointer, $this->contextualParser);

        $this->assertSpecificationAccessor_findResolvedObject_wasCalledOnceWithReference(self::REFERENCE);
        $this->assertSpecificationAccessor_setResolvedObject_wasCalledOnceWithReferenceAndObject(self::REFERENCE, $object);
        $this->assertContextualParser_parsePointedSchema_wasCalledOnceWithSpecificationAndPointerPath($this->specificationAccessor, ['reference']);
        $this->assertSame($expectedObject, $object);
    }

    /** @test */
    public function parseObject_schemaWithResolvedReference_objectReturnedFromSpecification(): void
    {
        $objectParser = new SpecificationObjectParser();
        $pointer = new SpecificationPointer();
        $this->givenSpecificationAccessor_getSchema_returnsSchema(['$ref' => self::REFERENCE]);
        $expectedObject = $this->givenSpecificationAccessor_findResolvedObject_returnsObject();

        $object = $objectParser->parseObject($this->specificationAccessor, $pointer, $this->contextualParser);

        $this->assertSpecificationAccessor_findResolvedObject_wasCalledOnceWithReference(self::REFERENCE);
        $this->assertSpecificationAccessor_setResolvedObject_wasNeverCalledOnceWithAnyParameters();
        $this->assertContextualParser_parsePointedSchema_wasNeverCalledWithAnyParameters();
        $this->assertSame($expectedObject, $object);
    }

    public function invalidReferenceAndExceptionMessageProvider(): array
    {
        return [
            ['', 'reference cannot be empty'],
            ['file', 'only local references is supported - reference must start with "#/"'],
        ];
    }

    private function assertSpecificationAccessor_getSchema_wasCalledOnceWithPointer(SpecificationPointer $pointer): void
    {
        \Phake::verify($this->specificationAccessor)
            ->getSchema($pointer);
    }

    private function givenSpecificationAccessor_getSchema_returnsSchema(array $schema): void
    {
        \Phake::when($this->specificationAccessor)
            ->getSchema(\Phake::anyParameters())
            ->thenReturn($schema);
    }

    private function assertSpecificationAccessor_findResolvedObject_wasCalledOnceWithReference(string $reference): void
    {
        \Phake::verify($this->specificationAccessor)
            ->findResolvedObject($reference);
    }

    private function assertSpecificationAccessor_setResolvedObject_wasCalledOnceWithReferenceAndObject(string $reference, SpecificationObjectMarkerInterface $object): void
    {
        \Phake::verify($this->specificationAccessor)
            ->setResolvedObject($reference, $object);
    }

    private function assertSpecificationAccessor_setResolvedObject_wasNeverCalledOnceWithAnyParameters(): void
    {
        \Phake::verify($this->specificationAccessor, \Phake::never())
            ->setResolvedObject(\Phake::anyParameters());
    }

    private function givenSpecificationAccessor_findResolvedObject_returnsNull(): void
    {
        \Phake::when($this->specificationAccessor)
            ->findResolvedObject(\Phake::anyParameters())
            ->thenReturn(null);
    }

    private function givenSpecificationAccessor_findResolvedObject_returnsObject(): SpecificationObjectMarkerInterface
    {
        $object = \Phake::mock(SpecificationObjectMarkerInterface::class);

        \Phake::when($this->specificationAccessor)
            ->findResolvedObject(\Phake::anyParameters())
            ->thenReturn($object);

        return $object;
    }
}