<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ProxyManagerTest\Functional;

use PHPUnit_Framework_TestCase;
use ProxyManager\Factory\RemoteObject\Adapter\JsonRpc as JsonRpcAdapter;
use ProxyManager\Factory\RemoteObject\Adapter\XmlRpc as XmlRpcAdapter;
use ProxyManager\Generator\ClassGenerator;
use ProxyManager\Generator\Util\UniqueIdentifierGenerator;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\ProxyGenerator\RemoteObjectGenerator;
use ProxyManagerTestAsset\ClassWithSelfHint;
use ProxyManagerTestAsset\RemoteProxy\Foo;
use ReflectionClass;
use Zend\Server\Client;

/**
 * Tests for {@see \ProxyManager\ProxyGenerator\RemoteObjectGenerator} produced objects
 *
 * @author Vincent Blanchon <blanchon.vincent@gmail.com>
 * @license MIT
 *
 * @group Functional
 * @coversNothing
 */
class RemoteObjectFunctionalTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param mixed  $expectedValue
     * @param string $method
     * @param array  $params
     *
     * @return XmlRpcAdapter
     */
    protected function getXmlRpcAdapter($expectedValue, $method, array $params)
    {
        /* @var $client Client|\PHPUnit_Framework_MockObject_MockObject */
        $client = $this->getMock(Client::class, ['call']);

        $client
            ->expects($this->any())
            ->method('call')
            ->with($this->stringEndsWith($method), $params)
            ->will($this->returnValue($expectedValue));

        $adapter = new XmlRpcAdapter(
            $client,
            [
                 'ProxyManagerTestAsset\RemoteProxy\Foo.foo'
                     => 'ProxyManagerTestAsset\RemoteProxy\FooServiceInterface.foo'
            ]
        );

        return $adapter;
    }

    /**
     * @param mixed  $expectedValue
     * @param string $method
     * @param array  $params
     *
     * @return JsonRpcAdapter
     */
    protected function getJsonRpcAdapter($expectedValue, $method, array $params)
    {
        /* @var $client Client|\PHPUnit_Framework_MockObject_MockObject */
        $client = $this->getMock(Client::class, ['call']);

        $client
            ->expects($this->any())
            ->method('call')
            ->with($this->stringEndsWith($method), $params)
            ->will($this->returnValue($expectedValue));

        $adapter = new JsonRpcAdapter(
            $client,
            [
                 'ProxyManagerTestAsset\RemoteProxy\Foo.foo'
                    => 'ProxyManagerTestAsset\RemoteProxy\FooServiceInterface.foo'
            ]
        );

        return $adapter;
    }

    /**
     * @dataProvider getProxyMethods
     *
     * @param string|object $instanceOrClassName
     * @param string        $method
     * @param mixed[]       $params
     * @param mixed         $expectedValue
     */
    public function testXmlRpcMethodCalls($instanceOrClassName, $method, array $params, $expectedValue)
    {
        $proxyName = $this->generateProxy($instanceOrClassName);

        /* @var $proxy \ProxyManager\Proxy\RemoteObjectInterface */
        $proxy     = $proxyName::staticProxyConstructor($this->getXmlRpcAdapter($expectedValue, $method, $params));

        $this->assertSame($expectedValue, call_user_func_array([$proxy, $method], $params));
    }

    /**
     * @dataProvider getProxyMethods
     *
     * @param string|object $instanceOrClassName
     * @param string        $method
     * @param mixed[]       $params
     * @param mixed         $expectedValue
     */
    public function testJsonRpcMethodCalls($instanceOrClassName, $method, array $params, $expectedValue)
    {
        $proxyName = $this->generateProxy($instanceOrClassName);

        /* @var $proxy \ProxyManager\Proxy\RemoteObjectInterface */
        $proxy     = $proxyName::staticProxyConstructor($this->getJsonRpcAdapter($expectedValue, $method, $params));

        $this->assertSame($expectedValue, call_user_func_array([$proxy, $method], $params));
    }

    /**
     * @dataProvider getPropertyAccessProxies
     *
     * @param string|object $instanceOrClassName
     * @param string        $publicProperty
     * @param string        $propertyValue
     */
    public function testJsonRpcPropertyReadAccess($instanceOrClassName, $publicProperty, $propertyValue)
    {
        $proxyName = $this->generateProxy($instanceOrClassName);

        /* @var $proxy \ProxyManager\Proxy\RemoteObjectInterface */
        $proxy     = $proxyName::staticProxyConstructor(
            $this->getJsonRpcAdapter($propertyValue, '__get', [$publicProperty])
        );

        /* @var $proxy \ProxyManager\Proxy\NullObjectInterface */
        $this->assertSame($propertyValue, $proxy->$publicProperty);
    }

    /**
     * Generates a proxy for the given class name, and retrieves its class name
     *
     * @param string $parentClassName
     *
     * @return string
     */
    private function generateProxy($parentClassName)
    {
        $generatedClassName = __NAMESPACE__ . '\\' . UniqueIdentifierGenerator::getIdentifier('Foo');
        $generator          = new RemoteObjectGenerator();
        $generatedClass     = new ClassGenerator($generatedClassName);
        $strategy           = new EvaluatingGeneratorStrategy();

        $generator->generate(new ReflectionClass($parentClassName), $generatedClass);
        $strategy->generate($generatedClass);

        return $generatedClassName;
    }

    /**
     * Generates a list of object | invoked method | parameters | expected result
     *
     * @return array
     */
    public function getProxyMethods()
    {
        $selfHintParam = new ClassWithSelfHint();

        return [
            [
                'ProxyManagerTestAsset\RemoteProxy\FooServiceInterface',
                'foo',
                [],
                'bar remote'
            ],
            [
                'ProxyManagerTestAsset\RemoteProxy\Foo',
                'foo',
                [],
                'bar remote'
            ],
            [
                new Foo(),
                'foo',
                [],
                'bar remote'
            ],
            [
                'ProxyManagerTestAsset\RemoteProxy\BazServiceInterface',
                'baz',
                ['baz'],
                'baz remote'
            ],
            [
                new ClassWithSelfHint(),
                'selfHintMethod',
                [$selfHintParam],
                $selfHintParam
            ],
        ];
    }

    /**
     * Generates proxies and instances with a public property to feed to the property accessor methods
     *
     * @return array
     */
    public function getPropertyAccessProxies()
    {
        return [
            [
                'ProxyManagerTestAsset\RemoteProxy\FooServiceInterface',
                'publicProperty',
                'publicProperty remote',
            ],
        ];
    }
}
