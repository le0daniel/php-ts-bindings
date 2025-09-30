<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\PHPStan;


use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectShape;
use PHPStan\Type\ObjectShapeType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Reflection\ReflectionProvider;

final class UtilitiesNodeResolver implements TypeNodeResolverExtension, TypeNodeResolverAwareExtension
{
    private TypeNodeResolver $typeNodeResolver;

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
    {
        $this->typeNodeResolver = $typeNodeResolver;
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        if (!$typeNode instanceof GenericTypeNode) {
            // returning null means this extension is not interested in this node
            return null;
        }

        $typeName = $typeNode->type;
        if (!in_array($typeName->name, ['Pick', 'Omit'], true)) {
            return null;
        }

        $arguments = $typeNode->genericTypes;
        if (count($arguments) !== 2) {
            return null;
        }

        $structType = $this->typeNodeResolver->resolve($arguments[0], $nameScope);
        $keysType = $this->typeNodeResolver->resolve($arguments[1], $nameScope);

        if ($structType->isObject()->yes()) {
            return match ($structType::class) {
                ObjectType::class => $this->resolveObjectType($typeName->name, $structType, $keysType),
                ObjectShapeType::class => $this->resolveObjectShapeType($typeName->name, $structType, $keysType),
                default => null,
            };
        }

        if ($structType->isConstantArray()->yes()) {
            /** @phpstan-ignore-next-line argument.type */
            return $this->resolveConstArrayType($typeName->name, $structType, $keysType);
        }

        return null;
    }

    /**
     * @param "Pick"|"Omit" $type
     * @param ConstantArrayType $structType
     * @param Type $keysType
     * @return Type
     */
    private function resolveConstArrayType(string $type, ConstantArrayType $structType, Type $keysType): Type
    {
        $newTypeBuilder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($structType->getKeyTypes() as $i => $keyType) {
            $isPropertyInArrayStruct = match ($type) {
                'Pick' => $keysType->isSuperTypeOf($keyType)->yes(),
                'Omit' => !$keysType->isSuperTypeOf($keyType)->yes(),
            };

            if (!$isPropertyInArrayStruct) {
                // eliminate keys that aren't in the Pick type
                continue;
            }

            $valueType = $structType->getValueTypes()[$i];
            $newTypeBuilder->setOffsetValueType(
                $keyType,
                $valueType,
                $structType->isOptionalKey($i),
            );
        }

        return $newTypeBuilder->getArray();
    }

    /**
     * @param "Pick"|"Omit" $type
     * @param ObjectType $structType
     * @param Type $keysType
     * @return Type
     * @throws \Exception
     */
    private function resolveObjectType(string $type, ObjectType $structType, Type $keysType): Type
    {
        $className = $structType->getClassName();
        $classReflection = $this->reflectionProvider->getClass($className);

        $properties = $classReflection->getNativeReflection()->getProperties(
            \ReflectionProperty::IS_PUBLIC
        );
        $propertyTypes = [];

        /** @var \ReflectionProperty $prop */
        foreach ($properties as $prop) {
            $propName = $prop->getName();
            $keyType = new ConstantStringType($propName, false);
            $isPropertyInNewObject = match ($type) {
                'Pick' => $keysType->isSuperTypeOf($keyType)->yes(),
                'Omit' => !$keysType->isSuperTypeOf($keyType)->yes(),
            };

            if (!$isPropertyInNewObject) {
                continue;
            }

            // Ask PHPStan's ClassReflection about the property
            if ($classReflection->hasProperty($propName)) {
                $propertyReflection = $classReflection->getNativeProperty($propName);
                $propertyTypes[$propName] = $propertyReflection->getReadableType();
                continue;
            }

            throw new \Exception("Property {$propName} not found");
        }

        return new ObjectShapeType($propertyTypes, []);
    }

    /**
     * @param "Pick"|"Omit" $type
     * @param ObjectShapeType $structType
     * @param Type $keysType
     * @return Type
     */
    private function resolveObjectShapeType(string $type, ObjectShapeType $structType, Type $keysType): Type
    {
        /** @var array<string, Type> $newObjectProperties */
        $newObjectProperties = [];
        $optionalProperties = [];

        foreach ($structType->getProperties() as $propertyName => $propertyType) {
            $keyType = new ConstantStringType($propertyName, false);
            $isPropertyInNewObject = match ($type) {
                'Pick' => $keysType->isSuperTypeOf($keyType)->yes(),
                'Omit' => !$keysType->isSuperTypeOf($keyType)->yes(),
            };

            if (!$isPropertyInNewObject) {
                continue;
            }

            $newObjectProperties[$propertyName] = $propertyType;
            if (in_array($propertyName, $structType->getOptionalProperties(), true)) {
                $optionalProperties[] = $propertyName;
            }
        }

        return new ObjectShapeType($newObjectProperties, $optionalProperties);
    }
}