<?php

/**
 * PHP Service Bus (CQS implementation)
 *
 * @author  Maksim Masiukevich <desperado@minsk-info.ru>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace Desperado\ServiceBus\MessageProcessor;

use Desperado\Domain\Message\AbstractCommand;
use Desperado\Domain\Message\AbstractEvent;
use Desperado\Domain\Message\AbstractMessage;
use Desperado\Domain\ThrowableFormatter;
use Desperado\Saga\Service\SagaService;
use Desperado\ServiceBus\Application\Exceptions\OutboundContextNotAppliedException;
use Desperado\ServiceBus\Transport\Context\OutboundMessageContext;
use Desperado\Domain\Transport\Message\MessageDeliveryOptions;
use Psr\Log\LogLevel;

/**
 * Base class of the context of message processing
 */
abstract class AbstractExecutionContext
{
    /**
     * Outbound context
     *
     * @var OutboundMessageContext|null
     */
    private $outboundMessageContext;

    /**
     * Sagas service
     *
     * @var SagaService
     */
    private $sagaService;

    /**
     * @param SagaService $sagaService
     */
    public function __construct(SagaService $sagaService)
    {
        $this->sagaService = $sagaService;
    }

    /**
     * Apply a context to send the message to the transport layer
     * Must return a NEW instance of the class (immutable object)
     *
     * @param OutboundMessageContext $outboundMessageContext
     *
     * @return $this
     */
    abstract public function applyOutboundMessageContext(OutboundMessageContext $outboundMessageContext);

    /**
     * Get outbound context
     *
     * @return OutboundMessageContext|null
     */
    abstract protected function getOutboundMessageContext(): ?OutboundMessageContext;

    /**
     * Get saga service
     *
     * @return SagaService
     *
     * @throws \Exception
     */
    final public function getSagaService(): SagaService
    {
        return $this->sagaService;
    }

    /**
     * Log message in execution context
     *
     * @todo: fix me
     *
     * @param AbstractMessage $message
     * @param string          $logMessage
     * @param string          $level
     * @param array           $extra
     *
     * @return void
     */
    final public function logContextMessage(
        AbstractMessage $message,
        string $logMessage,
        string $level = LogLevel::INFO,
        array $extra = []
    ): void
    {

    }

    /**
     * Log Throwable in execution context
     *
     * @param AbstractMessage $message
     * @param \Throwable      $throwable ,
     * @param string          $level
     * @param array           $extra
     *
     * @return void
     */
    public function logContextThrowable(
        AbstractMessage $message,
        \Throwable $throwable,
        string $level = LogLevel::ERROR,
        array $extra = []
    ): void
    {
        $this->logContextMessage($message, ThrowableFormatter::toString($throwable), $level, $extra);
    }

    /**
     * Get logger callable for execution context
     *
     * @param AbstractMessage $message
     * @param string          $level
     *
     * @return callable
     */
    public function getContextThrowableCallableLogger(
        AbstractMessage $message,
        string $level = LogLevel::ERROR
    ): callable
    {
        return function(\Throwable $throwable) use ($message, $level)
        {
            $this->logContextMessage($message, ThrowableFormatter::toString($throwable), $level);
        };
    }

    /**
     * Send command/publish message
     *
     * @param AbstractMessage             $message
     * @param MessageDeliveryOptions|null $messageDeliveryOptions
     *
     * @return void
     *
     * @throws OutboundContextNotAppliedException
     */
    final public function delivery(AbstractMessage $message, MessageDeliveryOptions $messageDeliveryOptions = null): void
    {
        $messageDeliveryOptions = $messageDeliveryOptions ?? MessageDeliveryOptions::create();

        $message instanceof AbstractCommand
            ? $this->send($message, $messageDeliveryOptions)
            /** @var AbstractEvent $message */
            : $this->publish($message, $messageDeliveryOptions);
    }

    /**
     * Publish event
     *
     * @param AbstractEvent          $event
     * @param MessageDeliveryOptions $messageDeliveryOptions
     *
     * @return void
     *
     * @throws OutboundContextNotAppliedException
     */
    final public function publish(AbstractEvent $event, MessageDeliveryOptions $messageDeliveryOptions): void
    {
        $this->guardOutboundContext();

        $this->outboundMessageContext->publish($event, $messageDeliveryOptions);
    }

    /**
     * Send command
     *
     * @param AbstractCommand        $command
     * @param MessageDeliveryOptions $messageDeliveryOptions
     *
     * @return void
     *
     * @throws OutboundContextNotAppliedException
     */
    final public function send(AbstractCommand $command, MessageDeliveryOptions $messageDeliveryOptions): void
    {
        $this->guardOutboundContext();

        $this->outboundMessageContext->send($command, $messageDeliveryOptions);
    }

    /**
     * @return void
     *
     * @throws OutboundContextNotAppliedException
     */
    private function guardOutboundContext(): void
    {
        if(null === $this->getOutboundMessageContext())
        {
            throw new OutboundContextNotAppliedException();
        }
    }
}
