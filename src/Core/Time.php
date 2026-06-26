<?php

declare(strict_types=1);

namespace Northrook\Core;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Northrook\Core;
use Northrook\Core\DateTime\DateFormat;
use Northrook\Core\DateTime\TimeZone;
use Stringable;

final class Time implements Stringable
{
    public readonly \DateTimeImmutable $immutable;

    public int $timestamp {
        get => $this->immutable->getTimestamp();
    }

    public DateTimeZone $timezone {
        get => $this->immutable->getTimezone();
    }

    public function __construct(
        int|string|\DateTimeInterface $dateTime = 'now',
        null|TimeZone|DateTimeZone $timeZone = TimeZone::AUTO,
    ) {
        $this->immutable = self::getImmutable(
            $dateTime,
            $timeZone,
        );
    }

    public function diff(
        DateTimeInterface $targetObject,
        bool $absolute = false,
    ): DateInterval {
        return $this->immutable->diff($targetObject, $absolute);
    }

    public function format(
        null|string|DateFormat $format = null,
        bool|string $segment = false,
    ): string {
        return date_format(
            $this->immutable,
            $format ?? Core::get()->dateFormat->value,
            $segment,
        );
    }

    public function getOffset(): int
    {
        return $this->immutable->getOffset();
    }

    public function __toString(): string
    {
        return $this->format(Core\DateTime\DateFormat::RFC3339);
    }

    /**
     * @param DateTimeInterface|int|string $when
     * @param null |TimeZone|DateTimeZone  $timezone
     *
     * @return DateTimeImmutable
     */
    public static function getImmutable(
        int|string|DateTimeInterface $when = 'now',
        null|TimeZone|DateTimeZone $timezone = TimeZone::AUTO,
    ): DateTimeImmutable {
        $timestamp = match (true) {
            $when instanceof DateTimeInterface => '@' . $when->getTimestamp(),
            is_string($when) => $when,
            is_int($when)    => '@' . $when,
        };
        $timezone = match (true) {
            $when instanceof DateTimeInterface => $when->getTimezone(),
            $timezone instanceof DateTimeZone => $timezone,
            $timezone instanceof TimeZone => $timezone->resolve(),
            default => null,
        };

        try {
            return new DateTimeImmutable(
                $timestamp,
                $timezone ?? Core::get()->timezone->resolve(),
            );
        } catch (Exception $exception) {
            $message = 'Unable to create a new DateTimeImmutable object: ' . $exception->getMessage();
            throw new InvalidArgumentException(
                $message,
                E_RECOVERABLE_ERROR,
                $exception,
            );
        }
    }
}
