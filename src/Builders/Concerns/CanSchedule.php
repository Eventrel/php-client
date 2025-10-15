<?php

namespace Eventrel\Builders\Concerns;

use Carbon\CarbonInterface;

/**
 * Provides scheduling functionality for delayed event delivery.
 * 
 * This trait allows events to be scheduled for future delivery
 * rather than being sent immediately. Useful for:
 * - Delayed notifications (trial expiry warnings, payment reminders)
 * - Coordinated event timing (scheduled announcements)
 * - Rate limiting (spreading events over time)
 * - Business logic timing (send invoice 24 hours after order)
 * 
 * Usage:
 * ```php
 * // Specific time
 * $builder->scheduleAt(now()->addDays(7))->send();
 * 
 * // Relative timing
 * $builder->scheduleIn(3600)->send();           // 1 hour from now
 * $builder->scheduleInMinutes(30)->send();      // 30 minutes
 * $builder->scheduleInHours(24)->send();        // 24 hours
 * ```
 */
trait CanSchedule
{
    /**
     * The scheduled delivery timestamp.
     * 
     * When set, the event will be queued for delivery at this
     * specific time rather than being sent immediately.
     */
    private ?CarbonInterface $scheduledAt = null;

    /**
     * Schedule the event for delivery at a specific time.
     * 
     * Accepts any Carbon datetime instance. The event will be
     * held and delivered at the specified time.
     * 
     * Example:
     * ```php
     * ->scheduleAt(Carbon::parse('2025-12-25 09:00:00'))
     * ->scheduleAt(now()->addWeeks(2))
     * ->scheduleAt(now()->nextWeekday())
     * ```
     *
     * @param CarbonInterface $scheduledAt The exact delivery datetime
     * @return $this Fluent interface
     */
    public function scheduleAt(CarbonInterface $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * Schedule the event for delivery after a number of seconds.
     * 
     * Calculates the delivery time as the current time plus the
     * specified number of seconds.
     * 
     * Example:
     * ```php
     * ->scheduleIn(60)      // 1 minute from now
     * ->scheduleIn(3600)    // 1 hour from now
     * ->scheduleIn(86400)   // 24 hours from now
     * ```
     *
     * @param int $seconds Number of seconds to wait before delivery
     * @return $this Fluent interface
     */
    public function scheduleIn(int $seconds): self
    {
        $this->scheduledAt = now()->addSeconds($seconds);

        return $this;
    }

    /**
     * Schedule the event for delivery after a number of minutes.
     * 
     * Convenience method for scheduling with minute precision.
     * More readable than using scheduleIn() with seconds.
     * 
     * Example:
     * ```php
     * ->scheduleInMinutes(5)     // 5 minutes from now
     * ->scheduleInMinutes(30)    // 30 minutes from now
     * ->scheduleInMinutes(1440)  // 24 hours from now
     * ```
     *
     * @param int $minutes Number of minutes to wait before delivery
     * @return $this Fluent interface
     */
    public function scheduleInMinutes(int $minutes): self
    {
        $this->scheduledAt = now()->addMinutes($minutes);

        return $this;
    }

    /**
     * Schedule the event for delivery after a number of hours.
     * 
     * Convenience method for scheduling with hour precision.
     * More readable than using scheduleIn() or scheduleInMinutes().
     * 
     * Example:
     * ```php
     * ->scheduleInHours(1)    // 1 hour from now
     * ->scheduleInHours(24)   // 24 hours from now
     * ->scheduleInHours(168)  // 1 week from now
     * ```
     *
     * @param int $hours Number of hours to wait before delivery
     * @return $this Fluent interface
     */
    public function scheduleInHours(int $hours): self
    {
        $this->scheduledAt = now()->addHours($hours);

        return $this;
    }

    /**
     * Get the scheduled delivery time.
     * 
     * Returns null if no scheduling has been configured,
     * indicating immediate delivery.
     *
     * @return CarbonInterface|null The scheduled delivery time, or null for immediate
     */
    public function getScheduledAt(): ?CarbonInterface
    {
        return $this->scheduledAt;
    }
}
