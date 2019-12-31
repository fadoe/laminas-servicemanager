<?php

/**
 * @see       https://github.com/laminas/laminas-servicemanager for the canonical source repository
 * @copyright https://github.com/laminas/laminas-servicemanager/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-servicemanager/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ServiceManager\AbstractFactory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;

class ReflectionBasedAbstractFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function nonClassRequestedNames()
    {
        return [
            'non-class-string' => ['non-class-string'],
        ];
    }

    /**
     * @dataProvider nonClassRequestedNames
     */
    public function testCanCreateReturnsFalseForNonClassRequestedNames($requestedName)
    {
        $factory = new ReflectionBasedAbstractFactory();
        $this->assertFalse($factory->canCreate($this->container->reveal(), $requestedName));
    }

    public function testFactoryInstantiatesClassDirectlyIfItHasNoConstructor()
    {
        $factory = new ReflectionBasedAbstractFactory();
        $instance = $factory($this->container->reveal(), TestAsset\ClassWithNoConstructor::class);
        $this->assertInstanceOf(TestAsset\ClassWithNoConstructor::class, $instance);
    }

    public function testFactoryInstantiatesClassDirectlyIfConstructorHasNoArguments()
    {
        $factory = new ReflectionBasedAbstractFactory();
        $instance = $factory($this->container->reveal(), TestAsset\ClassWithEmptyConstructor::class);
        $this->assertInstanceOf(TestAsset\ClassWithEmptyConstructor::class, $instance);
    }

    public function testFactoryRaisesExceptionWhenUnableToResolveATypeHintedService()
    {
        $this->container->has(TestAsset\SampleInterface::class)->willReturn(false);
        $this->container->has(\ZendTest\ServiceManager\AbstractFactory\TestAsset\SampleInterface::class)->willReturn(false);
        $this->container->has('config')->willReturn(false);
        $factory = new ReflectionBasedAbstractFactory();
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'Unable to create service "%s"; unable to resolve parameter "sample" using type hint "%s"',
            TestAsset\ClassWithTypeHintedConstructorParameter::class,
            TestAsset\SampleInterface::class
        ));
        $factory($this->container->reveal(), TestAsset\ClassWithTypeHintedConstructorParameter::class);
    }

    public function testFactoryRaisesExceptionForScalarParameters()
    {
        $factory = new ReflectionBasedAbstractFactory();
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'Unable to create service "%s"; unable to resolve parameter "foo" to a class, interface, or array type',
            TestAsset\ClassWithScalarParameters::class
        ));
        $instance = $factory($this->container->reveal(), TestAsset\ClassWithScalarParameters::class);
    }

    public function testFactoryInjectsConfigServiceForConfigArgumentsTypeHintedAsArray()
    {
        $config = ['foo' => 'bar'];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);

        $factory = new ReflectionBasedAbstractFactory();
        $instance = $factory($this->container->reveal(), TestAsset\ClassAcceptingConfigToConstructor::class);
        $this->assertInstanceOf(TestAsset\ClassAcceptingConfigToConstructor::class, $instance);
        $this->assertEquals($config, $instance->config);
    }

    public function testFactoryCanInjectKnownTypeHintedServices()
    {
        $sample = $this->prophesize(TestAsset\SampleInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(TestAsset\SampleInterface::class)->willReturn(true);
        $this->container->get(TestAsset\SampleInterface::class)->willReturn($sample);

        $factory = new ReflectionBasedAbstractFactory();
        $instance = $factory($this->container->reveal(), TestAsset\ClassWithTypeHintedConstructorParameter::class);
        $this->assertInstanceOf(TestAsset\ClassWithTypeHintedConstructorParameter::class, $instance);
        $this->assertSame($sample, $instance->sample);
    }

    public function testFactoryResolvesTypeHintsForServicesToWellKnownServiceNames()
    {
        $this->container->has('config')->willReturn(false);

        $validators = $this->prophesize(TestAsset\ValidatorPluginManager::class)->reveal();
        $this->container->has('ValidatorManager')->willReturn(true);
        $this->container->get('ValidatorManager')->willReturn($validators);

        $factory = new ReflectionBasedAbstractFactory([TestAsset\ValidatorPluginManager::class => 'ValidatorManager']);
        $instance = $factory(
            $this->container->reveal(),
            TestAsset\ClassAcceptingWellKnownServicesAsConstructorParameters::class
        );
        $this->assertInstanceOf(
            TestAsset\ClassAcceptingWellKnownServicesAsConstructorParameters::class,
            $instance
        );
        $this->assertSame($validators, $instance->validators);
    }

    public function testFactoryCanSupplyAMixOfParameterTypes()
    {
        $validators = $this->prophesize(TestAsset\ValidatorPluginManager::class)->reveal();
        $this->container->has('ValidatorManager')->willReturn(true);
        $this->container->get('ValidatorManager')->willReturn($validators);

        $sample = $this->prophesize(TestAsset\SampleInterface::class)->reveal();
        $this->container->has(TestAsset\SampleInterface::class)->willReturn(true);
        $this->container->get(TestAsset\SampleInterface::class)->willReturn($sample);

        $config = ['foo' => 'bar'];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);

        $factory = new ReflectionBasedAbstractFactory([TestAsset\ValidatorPluginManager::class => 'ValidatorManager']);
        $instance = $factory($this->container->reveal(), TestAsset\ClassWithMixedConstructorParameters::class);
        $this->assertInstanceOf(TestAsset\ClassWithMixedConstructorParameters::class, $instance);

        $this->assertEquals($config, $instance->config);
        $this->assertEquals([], $instance->options);
        $this->assertSame($sample, $instance->sample);
        $this->assertSame($validators, $instance->validators);
    }

    public function testFactoryWillUseDefaultValueWhenPresentForScalarArgument()
    {
        $this->container->has('config')->willReturn(false);
        $factory = new ReflectionBasedAbstractFactory();
        $instance = $factory(
            $this->container->reveal(),
            TestAsset\ClassWithScalarDependencyDefiningDefaultValue::class
        );
        $this->assertInstanceOf(TestAsset\ClassWithScalarDependencyDefiningDefaultValue::class, $instance);
        $this->assertEquals('bar', $instance->foo);
    }
}
