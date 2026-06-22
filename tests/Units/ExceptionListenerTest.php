<?php

declare(strict_types=1);

namespace App\Tests\Units;

use App\Enum\OrderStatus;
use App\EventListener\ExceptionListener;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotFulfillableException;
use App\Exception\OrderNotPayableException;
use App\Exception\OrderNotRefundableException;
use App\Service\ExceptionMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExceptionListenerTest extends TestCase
{
    public function testOrderNotFoundExceptionResponse(): void
    {
        $orderId = 1;
        $exception = OrderNotFoundException::withDefaultMsg($orderId);

        $listener = new ExceptionListener(
            new ExceptionMapper()
        );

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $listener($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('"order_not_found"', $response->getContent());
    }

    public function testOrderNotFulfillableExceptionResponse(): void
    {
        $exception = OrderNotFulfillableException::withDefaultMsg(OrderStatus::FULFILLED);

        $listener = new ExceptionListener(
            new ExceptionMapper()
        );

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $listener($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('"order_not_fulfillable"', $response->getContent());
    }

    public function testOrderNotPayableExceptionResponse(): void
    {
        $exception = OrderNotPayableException::withDefaultMsg(OrderStatus::PAID);

        $listener = new ExceptionListener(
            new ExceptionMapper()
        );

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $listener($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('"order_not_payable"', $response->getContent());
    }

    public function testOrderNotRefundableExceptionResponse(): void
    {
        $exception = OrderNotRefundableException::withDefaultMsg(OrderStatus::REFUNDED);

        $listener = new ExceptionListener(
            new ExceptionMapper()
        );

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $listener($event);

        $response = $event->getResponse();

        $this->assertNotNull($response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('"order_not_refundable"', $response->getContent());
    }

    public function testUnknownExceptionDoesntChangeTheResponse(): void
    {
        $exception = new \Exception('unknown');

        $listener = new ExceptionListener(
            new ExceptionMapper()
        );

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $listener($event);

        $this->assertNull($event->getResponse());
    }
}
