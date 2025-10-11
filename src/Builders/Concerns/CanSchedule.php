<?php

namespace Eventrel\Client\Builders\Concerns;

use Carbon\Carbon;

trait CanSchedule
{
    /**
     * The scheduled time for the event.
     * 
     * @var Carbon|null
     */
    private ?Carbon $scheduledAt = null;

    /**
     * Schedule event for specific time
     */
    public function scheduleAt(Carbon $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * Schedule event for a number of seconds from now
     */
    public function scheduleIn(int $seconds): self
    {
        $this->scheduledAt = now()->addSeconds($seconds);

        return $this;
    }

    /**
     * Schedule event for a number of minutes from now
     */
    public function scheduleInMinutes(int $minutes): self
    {
        $this->scheduledAt = now()->addMinutes($minutes);

        return $this;
    }

    /**
     * Schedule event for a number of hours from now
     */
    public function scheduleInHours(int $hours): self
    {
        $this->scheduledAt = now()->addHours($hours);

        return $this;
    }

    /**
     * Get the scheduled time
     */
    public function getScheduledAt(): ?Carbon
    {
        return $this->scheduledAt;
    }
}
